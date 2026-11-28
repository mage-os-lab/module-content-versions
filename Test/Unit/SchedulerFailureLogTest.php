<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Test\Unit;

use ArrayIterator;
use MageOS\ContentVersions\Model\Entities;
use MageOS\ContentVersions\Model\ResourceModel\Update as UpdateResource;
use MageOS\ContentVersions\Model\ResourceModel\Update\Collection;
use MageOS\ContentVersions\Model\ResourceModel\Update\CollectionFactory;
use MageOS\ContentVersions\Model\Scheduler;
use MageOS\ContentVersions\Model\Update;
use Magento\Cms\Model\Page;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

/**
 * Tests that a planned version failing at each cron run is logged once per distinct error, and still retried.
 */
class SchedulerFailureLogTest extends TestCase
{
    /**
     * @var array<string, mixed> the stored row of the update, with transactions rolled back like the database
     */
    private array $row = [];

    /**
     * @var string[]
     */
    private array $logged = [];

    /**
     * @var string error thrown by the content write, empty when the write succeeds
     */
    private string $writeError = '';

    /**
     * @var int
     */
    private int $writes = 0;

    /**
     * @var bool whether the page of the update was deleted
     */
    private bool $deleted = false;

    /**
     * Generates the collection factory, as Magento's unit test bootstrap does.
     */
    public static function setUpBeforeClass(): void
    {
        $io = new Io(new File(), sys_get_temp_dir() . '/mageos-content-versions-generated');
        $loader = new GeneratedClassesAutoloader([new FactoryGenerator()], $io);
        spl_autoload_register(static function (string $class) use ($loader): void {
            $loader->load($class);
        });
    }

    /**
     * The same error is logged once; a new error, or a failure after a success, is logged again.
     */
    public function testSameErrorIsLoggedOnceAndRetriedAtEachRun(): void
    {
        $this->writeError = 'Connection lost';
        $this->runs(3);
        $this->assertSame(['Connection lost'], $this->logged);
        $this->assertSame(3, $this->writes, 'retried at each run');
        $this->assertSame('Connection lost', $this->row['error']);

        $this->writeError = 'Disk full';
        $this->runs(2);
        $this->assertSame(['Connection lost', 'Disk full'], $this->logged);

        $this->writeError = '';
        $this->runs(1);
        $this->assertNull($this->row['error']);
        $this->assertSame(Update::STATUS_ACTIVE, $this->row['status']);
        $this->row['status'] = Update::STATUS_SCHEDULED;
        $this->writeError = 'Disk full';
        $this->runs(1);
        $this->assertSame(['Connection lost', 'Disk full', 'Disk full'], $this->logged);
    }

    /**
     * A version whose page no longer exists is closed without error, log or write, and never retried.
     */
    public function testVersionOfDeletedContentIsFinishedWithoutError(): void
    {
        $this->deleted = true;
        $this->runs(2);
        $this->assertSame(Update::STATUS_FINISHED, $this->row['status']);
        $this->assertNull($this->row['error']);
        $this->assertSame([], $this->logged);
        $this->assertSame(0, $this->writes);
    }

    /**
     * Runs the scheduler, each run loading the update from the stored row as the cron does.
     *
     * @param int $times
     */
    private function runs(int $times): void
    {
        if (!$this->row) {
            $this->row = [
                'update_id' => 2,
                'entity_type' => 'page',
                'entity_id' => 5,
                'name' => 'Black Friday',
                'status' => Update::STATUS_SCHEDULED,
                'start_time' => '2026-01-01 00:00:00',
                'end_time' => '2099-01-01 00:00:00',
                'data' => json_encode(['content' => 'Sale']),
                'error' => null,
            ];
        }
        for ($i = 0; $i < $times; $i++) {
            $this->scheduler()->run();
        }
    }

    /**
     * A scheduler over the stored row, whose content write fails with the current error.
     */
    private function scheduler(): Scheduler
    {
        $update = (new ReflectionClass(Update::class))->newInstanceWithoutConstructor();
        $update->setData($this->row);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturnCallback(function () use ($update): Collection {
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnSelf();
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('getSize')->willReturn(0);
            $collection->method('getIterator')->willReturnCallback(
                fn () => new ArrayIterator(
                    $this->row['status'] === Update::STATUS_SCHEDULED ? [$update] : []
                )
            );
            return $collection;
        });

        $entities = $this->createStub(Entities::class);
        $entities->method('load')->willReturnCallback(function () {
            if ($this->deleted) {
                throw new NoSuchEntityException(__('The CMS page with the "5" ID doesn\'t exist.'));
            }
            return (new ReflectionClass(Page::class))->newInstanceWithoutConstructor();
        });
        $entities->method('write')->willReturnCallback(function (): void {
            $this->writes++;
            if ($this->writeError) {
                throw new RuntimeException($this->writeError);
            }
        });

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('update')->willReturnCallback(function (string $table, array $bind, array $where): int {
            if (isset($where['status = ?']) && $where['status = ?'] !== $this->row['status']) {
                return 0;
            }
            $this->row = array_replace($this->row, $bind);
            return 1;
        });
        $resource = $this->createStub(UpdateResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('mageos_versions_update');
        $saved = [];
        $resource->method('beginTransaction')->willReturnCallback(function () use (&$saved, $resource) {
            $saved = $this->row;
            return $resource;
        });
        $resource->method('rollBack')->willReturnCallback(function () use (&$saved, $resource) {
            $this->row = $saved;
            return $resource;
        });

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string $message): void {
            $this->logged[] = substr($message, strrpos($message, ': ') + 2);
        });

        return new Scheduler($collections, $entities, $resource, $logger);
    }
}
