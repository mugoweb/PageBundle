<?php

namespace MugoWeb\PageBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;

class UpdateLegacyDataCommand extends Command {

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var \Ibexa\Core\Repository\SiteAccessAware\Repository
     */
    protected $repository;
    /**
     * @var \MugoWeb\PageBundle\Services\MugoPageServic
     */
    protected $pageService;

    public function __construct(ContainerInterface $container,
            \Ibexa\Core\Repository\SiteAccessAware\Repository $repository,
            \MugoWeb\PageBundle\Services\MugoPageService $pageService) {
        parent::__construct(null);
        $this->container = $container;
        $this->repository = $repository;
        $this->pageService = $pageService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
                ->setName('mugopage:ezflow:data')
                ->addArgument('source', InputArgument::OPTIONAL, 'source')
                ->addArgument('target', InputArgument::OPTIONAL, 'target')
                ->addOption(
                        'script_user', 'u', InputOption::VALUE_OPTIONAL, 'eZ Platform username (with Role containing at least Content policies: read, versionread, edit, remove, versionremove)', 'admin'
                )
                ->setDescription('Migrate ezpage data to mugopage');
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
        $output->writeln('Starting update of legacy data...');

        $contentTypeService = $this->repository->getContentTypeService();
        $contentTypeGroups = $contentTypeService->loadContentTypeGroups();
        $updateData = [];
        $updateDataDetails = [];
        foreach ($contentTypeGroups as $contentTypeGroup) {
            $contentTypes = $contentTypeService->loadContentTypes($contentTypeGroup);

            foreach ($contentTypes as $contentType) {
                $ezpageFields = [];
                $existingFields = [];
                foreach ($contentType->getFieldDefinitions() as $fieldDefinition) {
                    $existingFields[$fieldDefinition->identifier] = true;
                    if ($fieldDefinition->fieldTypeIdentifier === 'ezpage') {
                        $ezpageFields[] = $fieldDefinition;
                        $updateData[] = ['class' => $contentType->identifier, 'field' => str_replace('_deprecated', '', $fieldDefinition->identifier)];
                    }
                }
                // now clean $ezpageFields array if there is already a "_deprecated" field
                $filteredEzpageFields = [];
                foreach ($ezpageFields as $field) {
                    if (!str_ends_with($field->identifier, '_deprecated')) {
                        $deprecatedIdentifier = $field->identifier . '_deprecated';
                        if (!isset($existingFields[$deprecatedIdentifier])) {
                            $filteredEzpageFields[] = $field;
                        }
                    }
                }

                $ezpageFields = $filteredEzpageFields;

                if (empty($ezpageFields)) {
                    continue;
                }
                $this->repository->sudo(function () use ($contentTypeService, $contentType, $ezpageFields, $output) {
                    $contentTypeDraft = $contentTypeService->createContentTypeDraft($contentType);

                    $maxPosition = 0;
                    foreach ($contentTypeDraft->getFieldDefinitions() as $fd) {
                        if ($fd->position > $maxPosition) {
                            $maxPosition = $fd->position;
                        }
                    }

                    foreach ($ezpageFields as $fieldDefinition) {
                        $originalIdentifier = $fieldDefinition->identifier;
                        $originalPosition = $fieldDefinition->position;
                        $newDeprecatedIdentifier = $originalIdentifier . '_deprecated';

                        // Update the old field: change identifier and move to end
                        $fieldUpdateStruct = $contentTypeService->newFieldDefinitionUpdateStruct();
                        $fieldUpdateStruct->identifier = $newDeprecatedIdentifier;
                        $fieldUpdateStruct->position = $maxPosition + 10;
                        $contentTypeService->updateFieldDefinition($contentTypeDraft, $fieldDefinition, $fieldUpdateStruct);

                        // Add new field at original position
                        $newFieldCreateStruct = $contentTypeService->newFieldDefinitionCreateStruct(
                            $originalIdentifier,
                            'mugopage'
                        );
                        $newFieldCreateStruct->names = $fieldDefinition->names ?: ['eng-US' => $originalIdentifier];
                        $newFieldCreateStruct->descriptions = [];
                        $newFieldCreateStruct->fieldGroup = $fieldDefinition->fieldGroup;
                        $newFieldCreateStruct->position = $originalPosition;
                        $newFieldCreateStruct->isTranslatable = $fieldDefinition->isTranslatable;
                        $newFieldCreateStruct->isRequired = $fieldDefinition->isRequired;
                        $newFieldCreateStruct->isInfoCollector = $fieldDefinition->isInfoCollector;
                        $newFieldCreateStruct->isSearchable = $fieldDefinition->isSearchable;

                        $contentTypeService->addFieldDefinition($contentTypeDraft, $newFieldCreateStruct);

                        $output->writeln("Updated content type '{$contentType->identifier}': renamed '{$originalIdentifier}' to '{$newDeprecatedIdentifier}' at position " . ($maxPosition + 10) . ", added new '{$originalIdentifier}' (mugopage) at position {$originalPosition}");

                        $maxPosition += 10;
                    }

                    // Publish the draft
                    $contentTypeService->publishContentTypeDraft($contentTypeDraft);
                });
            }
        }
        $validZones = $this->pageService->getZones();
        $validLayoutus = $this->pageService->getLayouts();
        $validBlocks = $this->pageService->getBlocks();
        $legacyKernelClosure = $this->container->get("ezpublish_legacy.kernel");
        $updateItems = $legacyKernelClosure()->runCallback(
            function () use ($output, $updateData, $validZones, $validLayoutus, $validBlocks, &$updateDataDetails ){
                // now using ez publish legacy api lets start migrating the data
                // first lets get the unique class identifiers from $updateData
                $classList = [];
                foreach($updateData as $updateItem) {
                    if(!isset($classList[$updateItem['class']])) {
                        $classList[$updateItem['class']] = [];
                    }
                    $classList[$updateItem['class']][] = $updateItem['field'];
                }
                $parentNodeID = 1;
                $updateItems = [];
                foreach($classList as $classIdentifier => $classAttributes) {
                    $parameters = array(
                       'parent_node_id' => $parentNodeID,
                       'class_filter_type' => 'include',
                       'class_filter_array' => [$classIdentifier],
                       'limitation' => array(),
                       'limit' => 100,
                       'main_node_only' => true
                    );
                    $count = \eZFunctionHandler::execute( 'content', 'tree_count', $parameters );

                    $offset = 0;
                    $total = 0;
                    while( $offset <= $count )
                    {
                        unset(
                                $items,
                                $GLOBALS[ 'eZContentObjectContentObjectCache' ],
                                $GLOBALS[ 'eZContentObjectDataMapCache' ],
                                $GLOBALS[ 'eZContentObjectVersionCache' ]
                              );
                        $parameters[ 'offset' ] = $offset;
                        $items                  = \eZFunctionHandler::execute( 'content', 'tree', $parameters );
                        foreach( $items as $item )
                        {
                            $updateItem = ['id' => $item->attribute('contentobject_id'), 'url' => $item->attribute('url_alias'), 'data' => []];
                            $total++;
                            $dataMap = $item->attribute('data_map');
                            foreach($classAttributes as $classAttribute) {
                                $attrContent = $dataMap[$classAttribute . '_deprecated']->attribute('content');
                                $zoneLayout = $attrContent->attribute('zone_layout');
                                if(!isset($validLayoutus[$zoneLayout])) {
                                    continue;
                                }
                                $classAttributeData = ['layout' => $zoneLayout, 'zones' => []];
                                $this->updateDataDetails($updateDataDetails, $updateItem['id'], 'layout', $zoneLayout);
                                $zones = $attrContent->attribute('zones');
                                foreach($zones as $zone) {
                                    $zoneIdentifier = $zone->attribute('zone_identifier');
                                    if(!isset($validZones[$zoneIdentifier])) {
                                        continue;
                                    }
                                    $zoneData = ['identifier' => $zoneIdentifier, 'blocks' => []];
                                    $this->updateDataDetails($updateDataDetails, $updateItem['id'], 'zone', $zoneIdentifier);
                                    $zoneId = $zone->attribute('id');
                                    $blocks = $zone->attribute('blocks');
                                    if($blocks) {
                                        foreach($blocks as $block) {
                                            $blockType = $block->attribute('type');
                                            if(!isset($validBlocks[$blockType])) {
                                                continue;
                                            }
                                            $useBrowserMode = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'UseBrowseMode') ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'UseBrowseMode') : [];
                                            $blockData = [
                                                'type' => [
                                                    'identifier' => $blockType,
                                                    'name' => $validBlocks[$blockType]['data']['name']
                                                ],
                                                'content' => [
                                                    'name' => $block->attribute('name'),
                                                    'id' => $block->attribute('id')
                                                ],
                                            ];
                                            $this->updateDataDetails($updateDataDetails, $updateItem['id'], 'block', $blockType);
                                            $blockData['custom_attributes'] = [];
                                            if(isset($validBlocks[$blockType]['data']["attr"])) {
                                                $customAttributes = $block->attribute('custom_attributes');
                                                foreach($validBlocks[$blockType]['data']["attr"] as $attrId => $attrValue) {
                                                    if(isset($useBrowserMode[$attrValue['identifier']]))
                                                    {
                                                        $relationIds = [];
                                                        $nodeId = $customAttributes[$attrValue['identifier']];
                                                        if($nodeId) {
                                                            $node = \eZContentObjectTreeNode::fetch($nodeId);
                                                            if($node) {
                                                                $relationIds[] = ['locationId' => $customAttributes[$attrValue['identifier']], 'contentId' => $node->attribute('contentobject_id')];
                                                            }
                                                        }
                                                        $blockData['custom_attributes'][] = [
                                                            'identifier' => $attrValue['identifier'],
                                                            'type' => 'contentrelation',
                                                            'value' => $relationIds
                                                        ];
                                                    }
                                                    else
                                                    {
                                                        switch($attrValue['identifier']) {
                                                            case 'ManualItems':
                                                                $relationIds = [];
                                                                foreach($block->attribute('valid_nodes') as $validNode) {
                                                                    $relationIds[] = ['locationId' => $validNode->NodeID, 'contentId' => $validNode->ContentObjectID];
                                                                }
                                                                $blockData['custom_attributes'][] = [
                                                                    'identifier' => 'ManualItems',
                                                                    'type' => 'contentrelation',
                                                                    'value' => $relationIds
                                                                ];
                                                                break;
                                                            case 'ViewList':
                                                                var_dump('TODO: ViewList');exit;
                                                                break;
                                                            default:
                                                                switch($attrValue['type']) {
                                                                    case 'string':
                                                                    case 'text':
                                                                    case 'integer':
                                                                    case 'checkbox':
                                                                        $blockData['custom_attributes'][] = [
                                                                            'identifier' => $attrValue['identifier'],
                                                                            'type' => $attrValue['type'],
                                                                            'value' => $customAttributes[$attrValue['identifier']]
                                                                        ];
                                                                        break;
                                                                    case 'choice':
                                                                        $options = \eZINI::instance( 'block.ini' )->hasVariable( $blockType, 'CustomAttributeSelection_' .  $attrValue['identifier']) ? \eZINI::instance( 'block.ini' )->variable( $blockType, 'CustomAttributeSelection_' .  $attrValue['identifier']) : [];
                                                                        $blockData['custom_attributes'][] = [
                                                                            'identifier' => $attrValue['identifier'],
                                                                            'type' => $attrValue['type'],
                                                                            'value' => $options[$customAttributes[$attrValue['identifier']]]
                                                                        ];
                                                                        break;
                                                                    default:
                                                                        var_dump('TODO', $attrValue, $customAttributes, $useBrowserMode);exit;
                                                                }
                                                        }
                                                    }
                                                }
                                            }

                                            $zoneData['blocks'][] = $blockData;
                                        }
                                    }
                                    $classAttributeData['zones'][] = $zoneData;
                                }
                                $updateItem['data'][$classAttribute] = $classAttributeData;
                                /*
                                 * We will need to store in the mugopage data_text attribute a json structure like this:
                                {
                                    "layout":"layout_1",
                                    "zones":[
                                       {
                                          "identifier":"zone_1",
                                          "blocks":[
                                             {
                                                "type":{
                                                   "identifier":"block_1",
                                                   "name":"Block 1"
                                                },
                                                "content":{
                                                   "name":"Test block",
                                                   "id":"block_735615_1772575948657_113679"
                                                },
                                                "custom_attributes":[
                                                   {
                                                      "identifier":"checkbox",
                                                      "type":"checkbox",
                                                      "value":"1"
                                                   },
                                                   {
                                                      "identifier":"select",
                                                      "type":"choice",
                                                      "value":"1"
                                                   },
                                                   {
                                                      "identifier":"relation",
                                                      "type":"contentrelation",
                                                      "value":[
                                                         "41",
                                                         "1"
                                                      ]
                                                   },
                                                   {
                                                      "identifier":"integer",
                                                      "type":"integer",
                                                      "value":"2222"
                                                   },
                                                   {
                                                      "identifier":"string",
                                                      "type":"string",
                                                      "value":"test string"
                                                   },
                                                   {
                                                      "identifier":"text",
                                                      "type":"text",
                                                      "value":"text test\nHello"
                                                   }
                                                ]
                                             }
                                          ]
                                       }
                                    ]
                                 }
                                 */
                            }
                            $updateItems[] = $updateItem;
                        }
                        $offset += 100;
                    }
                }
                return $updateItems;
            });
        $repository = $this->repository;
        $repository->sudo(function () use ($repository, $updateItems, $output, $updateDataDetails) {
            foreach ($updateItems as $updateItem) {
                $output->writeln('/amnh2/' . $updateItem['url'] . " - " . $updateItem['id']);
                $output->writeln(json_encode($updateDataDetails[$updateItem['id']]));
                $contentId = (int)$updateItem['id'];
                $data      = $updateItem['data']; // ['identifier_1' => value, 'identifier_2' => value2, ...]

                try {
                    $contentService = $repository->getContentService();
                    $contentInfo = $contentService->loadContentInfo( $contentId );
                    $contentDraft = $contentService->createContentDraft( $contentInfo );
                    $contentUpdateStruct = $contentService->newContentUpdateStruct();

                    foreach ($data as $fieldIdentifier => $value) {
                        // store as JSON string
                        $jsonValue = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        $fieldValue = new \MugoWeb\PageBundle\FieldType\MugoPage\Value($jsonValue);
                        $contentUpdateStruct->setField(
                            $fieldIdentifier,
                            $fieldValue
                        );
                    }
                    $contentDraftUpdated = $contentService->updateContent( $contentDraft->versionInfo, $contentUpdateStruct );
                    $content = $contentService->publishVersion( $contentDraftUpdated->versionInfo );

                } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
                    // Content not found
                    error_log("Content #$contentId not found");
                } catch (\Exception $e) {
                    error_log("Failed to update content #$contentId: " . $e->getMessage());
                }
            }
        });

        $output->writeln('Done.');

        return Command::SUCCESS;
    }

    public function updateDataDetails(&$updateDataDetails, $id, $type, $data) {
        if(!isset($updateDataDetails[$id])) {
            $updateDataDetails[$id] = [];
        }
        if(!isset($updateDataDetails[$id][$type])) {
            $updateDataDetails[$id][$type] = [];
        }
        $updateDataDetails[$id][$type][] = $data;
    }
}
