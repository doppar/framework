<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMailCommand extends Command
{
    protected static $defaultName = 'make:mail';

    protected function configure()
    {
        $this
            ->setName('make:mail')
            ->setDescription('Creates a new Mailable class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Mailable class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $parts = explode('/', $name);

        $className = array_pop($parts);

        if (!str_ends_with($className, 'Mail')) {
            $className .= 'Mail';
        }

        $namespace = 'App\\Mail' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path() . '/app/Mail/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Mailable already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateMailContent($namespace, $className);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Mailable created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateMailContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Support\Mail\Mailable;
use Phaseolies\Support\Mail\Mailable\Subject;
use Phaseolies\Support\Mail\Mailable\Content;

class {$className} extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Define mail subject
     * @return Phaseolies\Support\Mail\Mailable\Subject
     */
    public function subject(): Subject
    {
        return new Subject(
            subject: 'New Mail'
        );
    }

    /**
     * Set the message body and data
     * @return Phaseolies\Support\Mail\Mailable\Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'Optional view.name',
            data: 'Optional data'
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachment(): array
    {
        return [];
    }
}
EOT;
    }
}
