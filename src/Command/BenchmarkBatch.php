<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:benchmark-batch')]
class BenchmarkBatch extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    /**
     * @throws MappingException
     * @throws QueryException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('biography', 'biography');

        // Warmup
        $query = $this->entityManager->createNativeQuery('select * from "user" limit 1', $rsm);
        $query->getResult();

        $this->mem('Before');
        $query = $this->entityManager->createNativeQuery('select * from "user" limit 5', $rsm);
        $row = $query->getResult();
        $this->mem('After');

        return Command::SUCCESS;
    }

    private function mem($label): void
    {
        echo (new \DateTime())->format("H:i:s") . "\t" . $label . "\t"
            ."Mem usage: " . memory_get_usage(true) / 1024 / 1024  . " Mb\t"
            ."Mem peak: " . memory_get_peak_usage(true) / 1024 / 1024  . " Mb"
            . PHP_EOL;
    }
}