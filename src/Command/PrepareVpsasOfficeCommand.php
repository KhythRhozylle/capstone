<?php

namespace App\Command;

use App\Entity\SasDirectorate;
use App\Entity\SasUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:prepare-vpsas-office', description: 'Ensure VPSAS and directorate appointment offices exist without replacing existing data.')]
class PrepareVpsasOfficeCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['VPSAS' => ['SDS', 'Office of the VPSAS'], 'SDS_OFFICE' => ['SDS', 'Office of the SDS Director'], 'SWS_OFFICE' => ['SWS', 'Office of the SWS Director'], 'ISPS_OFFICE' => ['ISPS', 'Office of the ISPS Director']] as $code => [$directorateCode, $name]) {
            if (!$this->manager->getRepository(SasUnit::class)->findOneBy(['code' => $code])) {
                $directorate = $this->manager->getRepository(SasDirectorate::class)->findOneBy(['code' => $directorateCode]) ?? throw new \RuntimeException('Directorate not configured: '.$directorateCode);
                $unit = new SasUnit($code, $name, $directorate);
                $unit->setHandlesDocuments(false);
                $this->manager->persist($unit);
            }
        }
        $this->manager->flush();
        $output->writeln('The VPSAS and directorate offices are ready for appointments.');
        return Command::SUCCESS;
    }
}
