<?php
declare(strict_types=1);

namespace MageOS\ContentVersions\Console\Command;

use MageOS\ContentVersions\Model\Scheduler;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Starts and ends the due planned versions now, as the cron job does.
 */
class Run extends Command
{
    /**
     * @param State $state
     * @param Scheduler $scheduler
     */
    public function __construct(
        private readonly State $state,
        private readonly Scheduler $scheduler
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('content-versions:run')
            ->setDescription('Start and end the due planned versions of CMS pages and blocks now');
    }

    /**
     * Run the cron job in the crontab area, so saves behave as they do from cron; fails if any version failed.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failures = $this->state->emulateAreaCode(Area::AREA_CRONTAB, [$this->scheduler, 'run']);
        foreach ($failures as $failure) {
            $output->writeln('<error>' . $failure . '</error>');
        }
        if ($failures) {
            return Command::FAILURE;
        }
        $output->writeln('Due planned versions applied.');
        return Command::SUCCESS;
    }
}
