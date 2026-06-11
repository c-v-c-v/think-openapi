<?php

namespace Cvcv\ThinkOpenApi\Command;

use Cvcv\ThinkOpenApi\OpenApi\Generator;
use Cvcv\ThinkOpenApi\OpenApi\OpenApiSpecStorage;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class GenerateOpenApi extends Command
{
    protected function configure()
    {
        $this->setName('docs:generate')
            ->addOption('output', 'o', Option::VALUE_OPTIONAL, 'OpenAPI JSON output path.')
            ->setDescription('Generate OpenAPI documentation from ThinkPHP routes and ApiDoc attributes.');
    }

    protected function execute(Input $input, Output $output)
    {
        $openApi = (new Generator($this->app))->generate();
        $target = (new OpenApiSpecStorage($this->app))->write($openApi, $input->getOption('output'));

        if ($target === null) {
            $output->writeln('<error>Failed to encode OpenAPI JSON.</error>');
            return 1;
        }

        $output->writeln(sprintf('<info>OpenAPI generated: %s</info>', $target));

        return 0;
    }
}
