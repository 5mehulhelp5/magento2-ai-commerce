<?php
declare(strict_types=1);

namespace Selectika\AiCommerce\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Selectika\AiCommerce\Model\ExportCatalogFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ExportCommand extends Command
{
    public function __construct(private ExportCatalogFactory $exporterFactory, private StoreManagerInterface $stores, private State $state, private ?LoggerInterface $logger = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('selectika:catalog:export')->setDescription('Publish a complete Selectika catalog snapshot for one store view')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store view code');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = (string) $input->getOption('store');
        if ($code === '' || $code === 'admin') {
            $output->writeln('<error>Pass --store with a storefront store view code.</error>');
            return 1;
        }
        try {
            $manifest = $this->state->emulateAreaCode(Area::AREA_CRONTAB, function () use ($code): array {
                // CLI commands are registered before setup has created the default website.
                // Defer the catalog dependency graph until this command actually runs.
                return $this->exporterFactory->create()->export((int) $this->stores->getStore($code)->getId());
            });
            $output->writeln(sprintf('Published snapshot %s: %d rows in %d pages.', $manifest['snapshot_id'], $manifest['total_rows'], count($manifest['pages'])));
            return 0;
        } catch (Throwable $error) {
            // Exception messages/traces can contain merchant values or credentials.
            $diagnostic = [
                'error_type' => get_debug_type($error),
                'error_file' => basename($error->getFile()),
                'error_line' => $error->getLine(),
            ];
            // Older compiled DI metadata may omit the new logger until setup recompiles it.
            $this->logger?->error('Selectika catalog export failed; prior snapshot retained.', $diagnostic);
            $output->writeln(sprintf(
                '<error>Catalog export failed (%s at %s:%d). Check Magento logs and configuration. The prior snapshot was retained.</error>',
                $diagnostic['error_type'], $diagnostic['error_file'], $diagnostic['error_line']
            ));
            return 1;
        }
    }
}
