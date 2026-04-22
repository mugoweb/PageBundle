<?php

namespace MugoWeb\PageBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;

// php bin/console mugopage:ezflow:settings --env=ENV --siteaccess=SITE
class UpdateLegacySettingsCommand extends Command {

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var \Ibexa\Core\Repository\SiteAccessAware\Repository
     */
    protected $repository;


    public function __construct(ContainerInterface $container, \Ibexa\Core\Repository\SiteAccessAware\Repository $repository) {
        parent::__construct(null);
        $this->container = $container;
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
                ->setName('mugopage:ezflow:settings')
                ->addOption(
                        'script_user', 'u', InputOption::VALUE_OPTIONAL, 'eZ Platform username (with Role containing at least Content policies: read, versionread, edit, remove, versionremove)', 'admin'
                )
                ->setDescription('Updates MugoPage\'s Layouts, Zones and Blocks according to eZ flow settings');
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        parent::initialize($input, $output);
        $this->repository->getPermissionResolver()->setCurrentUserReference(
                $this->repository->getUserService()->loadUserByLogin($input->getOption('script_user'))
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $legacyKernelClosure = $this->container->get("ezpublish_legacy.kernel");
        $legacyKernelClosure()->runCallback(
            function () use ($output){
                $db = \eZDB::instance();
                $classes = \eZContentClass::fetchAllClasses(false);
                $classMap = array();
                foreach ( $classes as $class )
                {
                    $classMap[ \eZContentClass::fetch($class['id'])->attribute('identifier') ] = $class['id'];
                }
                $zones = [];
                $allowedTypes = \eZINI::instance( 'zone.ini' )->variable( 'General', 'AllowedTypes' );
                $output->writeln("Adding layouts:");
                foreach($allowedTypes as $allowedType) {
                    $ZoneTypeName = \eZINI::instance( 'zone.ini' )->variable( $allowedType, 'ZoneTypeName' );
                    $LayoutZones = \eZINI::instance( 'zone.ini' )->variable( $allowedType, 'Zones' );
                    $LayoutZonesNames = \eZINI::instance( 'zone.ini' )->variable( $allowedType, 'ZoneName' );
                    foreach($LayoutZones as $LayoutZone) {
                        if(!isset($zones[$LayoutZone])) {
                            $zones[$LayoutZone] = $LayoutZonesNames[$LayoutZone];
                        }
                    }
                    $Template = str_replace('.tpl', '', \eZINI::instance( 'zone.ini' )->variable( $allowedType, 'Template' ));
                    $AvailableForClasses = \eZINI::instance( 'zone.ini' )->variable( $allowedType, 'AvailableForClasses' );
                    $contenttypes = [];
                    foreach($AvailableForClasses as $AvailableForClass) {
                        if(isset($classMap[$AvailableForClass])) {
                            $contenttypes[] = $classMap[$AvailableForClass];
                        }
                    }
                    $layout = [
                        'name' => $ZoneTypeName,
                        'identifier' => $allowedType,
                        'template' => "ibexadesign/mugopage/layouts/{$allowedType}.html.twig",
                        'contenttypes' => $contenttypes,
                        'zones' => $LayoutZones
                    ];
                    $idString = $db->escapeString( $allowedType );
                    $rows = $db->arrayQuery( "SELECT * FROM mugopage where type='layout' and identifier='{$idString}'" );
                    if(empty($rows)) {
                        $output->writeln($idString);
                        $dataStr = $db->escapeString(json_encode($layout));
                        $db->query( "INSERT INTO mugopage ( type, identifier, data) VALUES ( 'layout', '{$idString}', '{$dataStr}' )" );
                    }
                }
                $output->writeln("Adding zones:");
                foreach($zones as $zoneId => $zoneName) {
                    $idString = $db->escapeString( $zoneId );
                    $rows = $db->arrayQuery( "SELECT * FROM mugopage where type='zone' and identifier='{$idString}'" );
                    if(empty($rows)) {
                        $output->writeln($idString);
                        $dataStr = $db->escapeString(json_encode(['name' => $zoneName, 'identifier' => $zoneId, 'template' => "ibexadesign/mugopage/zones/{$zoneId}.html.twig",]));
                        $db->query( "INSERT INTO mugopage ( type, identifier, data) VALUES ( 'zone', '{$idString}', '{$dataStr}' )" );
                    }
                }
                // Blocks
                $blockTypes = \eZINI::instance( 'block.ini' )->variable( 'General', 'AllowedTypes' );
                $blocks = [];
                $output->writeln("Adding blocks:");
                foreach($blockTypes as $blockType) {
                    $blockName = \eZINI::instance( 'block.ini' )->variable( $blockType, 'Name' );
                    $template = "ibexadesign/mugopage/blocks/{$blockType}.html.twig";
                    $blockData = [
                        'name' => $blockName,
                        'identifier' => $blockType,
                        'description' => '',
                        'template' => $template,
                    ];
                    $viewList = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'ViewList' ) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'ViewList' ) : null;
                    $attributes = [];
                    if(!empty($viewList) && is_array($viewList) && count($viewList) > 1) {
                        $attributes[] = [
                            'name' => 'ViewList',
                            'identifier' => 'ViewList',
                            'choicetype' => 'select',
                            'options'=> implode("\r\n", $viewList)
                        ];
                    }
                    if(\eZINI::instance( 'block.ini' )->variable( $blockType, 'ManualAddingOfItems' ) == 'enabled')
                    {
                        $manualItems = [
                            'name' => 'ManualItems',
                            'identifier' => 'ManualItems',
                            'type' => 'contentrelation'
                        ];
                        $AvailableForClasses = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'AllowedClasses' ) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'AllowedClasses' ) : null;
                        if(!empty($AvailableForClasses) && is_array($AvailableForClasses)) {
                            $contenttypes = [];
                            foreach($AvailableForClasses as $AvailableForClass) {
                                if(isset($classMap[$AvailableForClass])) {
                                    $contenttypes[] = $classMap[$AvailableForClass];
                                }
                            }
                            $manualItems['allowedtypes'] = $contenttypes;
                        }
                        $manualItems['maximumitems'] = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'NumberOfValidItems' ) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'NumberOfValidItems' ) : '0';
                        $attributes[] = $manualItems;
                    }
                    if(\eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'AllowedZones' ))
                    {
                        $allowedZones = \eZINI::instance( 'block.ini' )->variable( $blockType, 'AllowedZones' );
                        $blockData['zones'] = array_filter($allowedZones, fn($v) => $v !== '' && $v !== null);
                    }
                    $customAttributeNames = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'CustomAttributeNames' ) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'CustomAttributeNames' ) : [];
                    $customAttributeTypes = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'CustomAttributeTypes' ) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'CustomAttributeTypes' ) : [];

                    if(\eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'CustomAttributes' )) {
                        $useBrowserMode = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'UseBrowseMode') ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'UseBrowseMode') : [];
                        foreach(\eZINI::instance( 'block.ini' )->variable( $blockType, 'CustomAttributes' ) as $customAttributeIdentifier) {
                            $customAttribute = [
                                'name' => $customAttributeNames[$customAttributeIdentifier] ?? $customAttributeIdentifier,
                                'identifier' => $customAttributeIdentifier,
                            ];
                            $attributeType = $customAttributeTypes[$customAttributeIdentifier] ?? 'string';
                            switch($attributeType) {
                                case 'select':
                                    $attributeType = 'choice';
                                    $customAttribute['choicetype'] = 'select';
                                    $options = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'CustomAttributeSelection_' .  $customAttributeIdentifier) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'CustomAttributeSelection_' .  $customAttributeIdentifier) : [];
                                    $options = array_filter($options, fn($v) => $v !== '' && $v !== null);
                                    $customAttribute['options'] = implode("\r\n", $options);
                                    break;
                                case 'string':
                                    // Browser Mode
                                    if(isset($useBrowserMode[$customAttributeIdentifier])) {
                                        $output->writeln("useBrowserMode: {$blockType} / {$customAttributeIdentifier}");
                                        $attributeType = 'contentrelation';
                                        $manualItems['maximumitems'] = '1';
                                    }
                                    break;
                            }
                            $customAttribute['type'] = $attributeType;
                            $attributes[] = $customAttribute;
                        }
                    }
                    foreach($attributes as $index => $attribute) {
                        $blockData['attr'][time() . '-' . ($index+1)] = $attribute;
                    }
                    $idString = $db->escapeString( $blockType );
                    $rows = $db->arrayQuery( "SELECT * FROM mugopage where type='block' and identifier='{$idString}'" );
                    if(empty($rows)) {
                        $output->writeln($idString);
                        $dataStr = $db->escapeString(json_encode($blockData));
                        $db->query( "INSERT INTO mugopage ( type, identifier, data) VALUES ( 'block', '{$idString}', '{$dataStr}' )" );
                    }
                }
            }
        );
        return Command::SUCCESS;
    }
}
