<?php

namespace MugoWeb\PageBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use \Doctrine\ORM\EntityManager;

// DEPRECATED
// Used to fix items in old format
// php bin/console mugopage:ezflow:patch-items --env=ENV --siteaccess=SITE
class PatchItemsCommand extends Command {

    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    public function __construct(EntityManager $entityManager) {
        parent::__construct(null);
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
                ->setName('mugopage:ezflow:patch-items')
                ->setDescription('Fix items in old format');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $connection = $this->entityManager->getConnection();

        $queryString =  "select * from ezcontentobject_attribute where data_type_string = 'mugopage' and data_text like '%contentrelation%'";

        $results = $connection->fetchAllAssociative($queryString, []);

        $items = [];
        foreach($results as $result) {
            $dataText = $result['data_text'];
            // 1. Decode (keep as associative array)
            $data = json_decode($dataText, true);

            // 2. Traverse and update every contentrelation
            foreach ($data['zones'] as &$zone) {
                foreach ($zone['blocks'] as &$block) {
                    if (empty($block['custom_attributes'])) {
                        continue;
                    }

                    foreach ($block['custom_attributes'] as &$attr) {
                        if (isset($attr['type']) && $attr['type'] === 'contentrelation') {
                            $items = [];
                            foreach ($attr['value'] as $locationId => $contentId) {
                                if(is_array($contentId)) {
                                    $items[] = $contentId;
                                }
                                else
                                {
                                    $items[] = [
                                        'locationId' => (int) $locationId,   // cast to integer if needed
                                        'contentId'  => (int) $contentId
                                    ];
                                }
                            }
                            $attr['value'] = $items;
                        }
                    }
                }
            }

            // 3. Encode back to JSON
            $newJsonString = json_encode($data);
            $sql = "
                UPDATE ezcontentobject_attribute 
                SET data_text = :data_text 
                WHERE id = :id
            ";

            $affectedRows = $connection->executeStatement($sql, [
                'data_text' => $newJsonString,
                'id'        => $result['id']
            ]);

            // Optional: check if the update happened
            if ($affectedRows > 0) {
                // Success
                $output->writeln("Updated $affectedRows row(s)");
            } else {
                // No row was updated (id not found or data same)
            }
        }

        return Command::SUCCESS;
    }
}
