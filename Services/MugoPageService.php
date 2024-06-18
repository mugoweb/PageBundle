<?php

namespace Mugo\PageBundle\Services;

use stdClass;
use \Doctrine\ORM\EntityManager;
use \Symfony\Component\DependencyInjection\Container;
use \Ibexa\Contracts\Core\Repository\ContentService;
use \Ibexa\Contracts\Core\Repository\ContentTypeService;

class MugoPageService
{

    /** @var \Doctrine\ORM\EntityManager */
	private $entityManager;

	/** @var \Ibexa\Contracts\Core\Repository\ContentTypeService */
	private $contentTypeService;

	/** @var \Symfony\Component\DependencyInjection\Container */
	private $container;

	/** @var \Ibexa\Contracts\Core\Repository\ContentService */
	private $contentService;

	/**
	 * Strings Definition
	 * These constants should match Resources/public/js/admin/config/script.js
	 */
	const ITEM_TYPE_ZONE = 'zone';
	const ITEM_TYPE_LAYOUT = 'layout';
	const ITEM_TYPE_BLOCK = 'block';

	const ITEM_ACTION_NEW = 'new';
	const ITEM_ACTION_EDIT = 'edit';

	/**
	 * Class constructor
	 *
	 * @param EntityManager $entityManager
	 * @param ContentTypeService $contentTypeService
	 * @param Container $container
	 */
    function __construct(
		EntityManager $entityManager,
		ContentTypeService $contentTypeService,
		Container $container,
		ContentService $contentService
	){
		$this->entityManager = $entityManager;
		$this->contentTypeService = $contentTypeService;
		$this->container = $container;
		$this->contentService = $contentService;
    }

	/**
	 * Search for type and identifier in the database
	 * @param String $type
	 * @param String $identifier
	 * @return mixed
	 */
	private function fetchTypeAndIdentifier(String $type, String $identifier){

		$queryParameters = array(
			'type' => $type,
			'identifier' => $identifier
		);
		$queryString =  "SELECT * FROM  `mugopage` WHERE `type` = :type AND `identifier` = :identifier";
		$connection = $this->entityManager->getConnection();
		return $connection->fetchAssociative($queryString, $queryParameters);

	}

	/**
	 * Saves the item based on $type and Post request
	 * @param $type
	 * @param $request
	 * @return void
	 */
    public function saveMugoPageConfiguration($type, $request){

		$actionType = $request->request->get('actiontype', self::ITEM_ACTION_NEW);
		$identifier = $request->request->get('identifier');

		// remove special charaters from identifier
		$identifier = preg_replace('/[^a-zA-Z0-9_\-+.]/', '', $identifier);

		if ($actionType == self::ITEM_ACTION_NEW){

			// check if type and identifier exist
			$hasItem = $this->fetchTypeAndIdentifier($type, $identifier);
			if($hasItem){
				return array(
					'type' => 'error',
					'message' => 'The ' . $identifier . ' ' . $type . ' already exist! Use a different identifier for this ' . $type,
				);
			}

			// prepare data
			$data = $request->request->all();

			unset($data['actiontype']);
			unset($data['itemtype']);

			if (array_key_exists('description', $data)){
				$data['description'] = preg_replace('/[^a-zA-Z0-9_\-+.\s]/m', '', $data['description']);
				if( $data['description'] == ''){
					unset($data['description']);
				}
			}

			// create insert parameter
			$insertParameters = array(
				'type' => $type,
				'identifier' => $identifier,
				'data' => json_encode($data, JSON_HEX_APOS)
			);

			$insertQuery = "INSERT INTO `mugopage` (`type`, `identifier`, `data`) VALUES (:type, :identifier, :data)";
			$connection = $this->entityManager->getConnection();

			$insertResult = $connection->executeStatement($insertQuery, $insertParameters);

			if ($insertResult) {
				return array(
					'type' => 'success',
					'message' => 'The ' . $identifier . ' ' . $type . ' was created successfully!',
					'itemtype' => $type,
				);
			} else {
				return array(
					'type' => 'error',
					'message' => 'There was an error creating ' . $identifier . ' ' . $type . '!',
				);
			}

		} elseif ($actionType == self::ITEM_ACTION_EDIT){

			// check if type and identifier exist
			$hasItem = $this->fetchTypeAndIdentifier($type, $identifier);
			if(!$hasItem){
				return array(
					'type' => 'error',
					'message' => 'The ' . $identifier . ' ' . $type . ' was not found!',
				);
			}

			// prepare data
			$data = $request->request->all();
			unset($data['actiontype']);
			unset($data['itemtype']);

			// create update parameter
			$updateParameters = array(
				'type' => $type,
				'identifier' => $identifier,
				'data' => json_encode($data, JSON_HEX_APOS)
			);

			$updateQuery = "UPDATE `mugopage` SET `data` = :data WHERE `type` = :type AND `identifier` = :identifier";
			$connection = $this->entityManager->getConnection();

			$response = false;

			try{

				$updateResult = $connection->executeStatement($updateQuery, $updateParameters);

			} catch (Exception $e) {

				$response =  array(
					'type' => 'error',
					'message' => 'There was an error updating ' . $identifier . ' ' . $type . '!',
					'updateParameters' => $updateParameters,
					'errormessage' => $e->getMessage()
				);

			}

			if ($response == false) {

				if ($updateResult) {
					$response =  array(
						'type' => 'success',
						'message' => 'The ' . $identifier . ' ' . $type . ' was updated successfully!',
						'itemtype' => $type,
					);
				} else {
					$response =  array(
						'type' => 'warning',
						'message' => 'There are no changes in the ' . $identifier . ' ' . $type . '!',
						'updateParameters' => $updateParameters,
					);
				}

			}

			$connection->close();

			return $response;

		}
    }

	/**
	 * Return all zones available in the database
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function getZones(){

		$queryParameters = array(
			'type' => self::ITEM_TYPE_ZONE,
		);
		$queryString =  "SELECT * FROM  `mugopage` WHERE `type` = :type";
		$connection = $this->entityManager->getConnection();
		$results = $connection->fetchAllAssociative($queryString, $queryParameters);

		$return = array();

		foreach ($results as $result){
			$return[$result['identifier']] = array(
				'type' => $result['type'],
				'identifier' => $result['identifier'],
				'data' => json_decode($result['data'], true),
			);
		}

		return $return;

	}

	/**
	 * Return all layouts available in the database
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function getLayouts(){

		$queryParameters = array(
			'type' => self::ITEM_TYPE_LAYOUT,
		);
		$queryString =  "SELECT * FROM  `mugopage` WHERE `type` = :type";
		$connection = $this->entityManager->getConnection();
		$results = $connection->fetchAllAssociative($queryString, $queryParameters);

		$return = array();

		foreach ($results as $result){
			$return[$result['identifier']] = array(
				'type' => $result['type'],
				'identifier' => $result['identifier'],
				'data' => json_decode($result['data'], true),
			);
		}

		return $return;

	}

	/**
	 * Return all blocks available in the database
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function getBlocks(){

		$queryParameters = array(
			'type' => self::ITEM_TYPE_BLOCK,
		);
		$queryString =  "SELECT * FROM  `mugopage` WHERE `type` = :type";
		$connection = $this->entityManager->getConnection();
		$results = $connection->fetchAllAssociative($queryString, $queryParameters);

		$return = array();

		foreach ($results as $result){
			$return[$result['identifier']] = array(
				'type' => $result['type'],
				'identifier' => $result['identifier'],
				'data' => json_decode($result['data'], true),
			);
		}

		return $return;

	}

	/**
	 * Remove a MugoPage Item based on type and identifier
	 */
	public function deleteMugoPageItem(String $type, String $identifier){

		// check if type and identifier exist
		$hasItem = $this->fetchTypeAndIdentifier($type, $identifier);
		if(!$hasItem){
			return array(
				'type' => 'error',
				'message' => 'The ' . $identifier . ' ' . $type . ' does exist!',
			);
		}

		$deleteParameters =  array(
			'type' => $type,
			'identifier' => $identifier
		);

		$deleteQuery = "DELETE FROM `mugopage` WHERE `type` = :type AND `identifier` = :identifier";

		$connection = $this->entityManager->getConnection();
		$deleteResult = $connection->executeStatement($deleteQuery, $deleteParameters);

		if ($deleteResult) {
			return array(
				'type' => 'success',
				'message' => 'The ' . $identifier . ' ' . $type . ' was removed successfully!',
				'itemtype' => $type,
				'itemidentifier' => $identifier,
			);
		} else {
			return array(
				'type' => 'error',
				'message' => 'There was an error deleting ' . $identifier . ' ' . $type . '!',
			);
		}

	}

	/**
	 * Validates type and data and returns an array with all options
	 * @param String $fieldValue
	 * @return void
	 */
	public function getFieldContentObject(String $fieldValue){

		$fieldContentObject = new stdClass();

		// convert field value
		$fieldValue = @json_decode($fieldValue, true);

		// prepare layout information
		if ( isset($fieldValue['layout']) && $fieldValue['layout']){

			$actLayout = $this->prepareLayoutData($fieldValue);
			if ($actLayout) {
				$fieldContentObject->layout = $actLayout;
			}

		}

		return $fieldContentObject;

	}

	/**
	 * Format Layout Data based on Input Field content
	 * @param array $layoutData
	 * @return stdClass
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException
	 */
	private function prepareLayoutData(Array $layoutData){

		$layout = new stdClass();

		// get field configuration
		$layoutsConfig = $this->getLayouts();

		// check if layoutData exists
		if (isset($layoutsConfig[$layoutData['layout']]) && $layoutsConfig[$layoutData['layout']] && isset($layoutsConfig[$layoutData['layout']]['data']) && $layoutsConfig[$layoutData['layout']]['data']){

			// create config
			$config = new stdClass();

			$config->name = ($layoutsConfig[$layoutData['layout']]['data']['name'] ?? '');
			$config->identifier = ($layoutsConfig[$layoutData['layout']]['identifier'] ?? '');
			$config->type = ($layoutsConfig[$layoutData['layout']]['type'] ?? '');
			$config->template = ($layoutsConfig[$layoutData['layout']]['data']['template'] ?? '');
			$config->zoneids = ($layoutsConfig[$layoutData['layout']]['data']['zones'] ?? array());

			// get default template
			if ($this->container->hasParameter('mugopage_settings')) {
				$mugoPageSettings = $this->container->getParameter('mugopage_settings');
				if ($mugoPageSettings && array_key_exists('default_templates', $mugoPageSettings) && array_key_exists('layout', $mugoPageSettings['default_templates'])) {
					$config->defaulttemplate = $mugoPageSettings['default_templates']['layout'];
				}
			}

			// layout available in content types
			$availableContentTypes = array();
			$availableContentTypeIds = array();
			if(isset($layoutsConfig[$layoutData['layout']]['data']['contenttypes'])){
				foreach($layoutsConfig[$layoutData['layout']]['data']['contenttypes'] as $contentTypeId){
					$contentTypeObj = $this->contentTypeService->loadContentType($contentTypeId);
					if ($contentTypeObj){
						$availableContentTypeIds[$contentTypeId] = $contentTypeId;
						$availableContentTypes[$contentTypeId] = array(
							'id' => $contentTypeObj->id,
							'identifier' => $contentTypeObj->identifier,
							'name' => $contentTypeObj->getName()
						);
					}
				}
			}
			$config->availablecontenttypeids = $availableContentTypeIds;
			$config->availablecontenttypes = $availableContentTypes;

			// get zones
			$zones = array();
			if (isset($layoutData['zones']) && $layoutData['layout']){
				$zones = $this->prepareZonesData($layoutData['zones']);
			}

			// updated object
			$layout->name = $config->name;
			$layout->identifier = $config->identifier;
			$layout->type = $config->type;
			$layout->config = $config;
			$layout->zones = $zones;

		}

		return $layout;

	}

	/**
	 * Format Zone Data based on Input Field content
	 * @param array $zonesData
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function prepareZonesData(Array $zonesData){

		$zones = array();

		// get field configuration
		$zonesConfig = $this->getZones();

		foreach ($zonesData as $zoneData) {

			if (isset($zonesConfig[$zoneData['identifier']]) && $zonesConfig[$zoneData['identifier']] && isset($zonesConfig[$zoneData['identifier']]['data']) && $zonesConfig[$zoneData['identifier']]['data']) {

				// create config
				$config = new stdClass();

				$config->name = ($zonesConfig[$zoneData['identifier']]['data']['name'] ?? '');
				$config->identifier = ($zonesConfig[$zoneData['identifier']]['identifier'] ?? '');
				$config->type = ($zonesConfig[$zoneData['identifier']]['type'] ?? '');

				// get default template
				if ($this->container->hasParameter('mugopage_settings')) {
					$mugoPageSettings = $this->container->getParameter('mugopage_settings');
					if ($mugoPageSettings && array_key_exists('default_templates', $mugoPageSettings) && array_key_exists('zone', $mugoPageSettings['default_templates'])) {
						$config->defaulttemplate = $mugoPageSettings['default_templates']['zone'];
					}
				}

				// get blocks
				$blocks = array();
				if (isset($zoneData['blocks']) && $zoneData['blocks']){
					$blocks = $this->prepareBlocksData($zoneData['blocks']);
				}

				// updated object
				$zone = new stdClass();
				$zone->name = $config->name;
				$zone->identifier = $config->identifier;
				$zone->type = $config->type;
				$zone->config = $config;
				$zone->blocks = $blocks;

				$zones[$config->identifier] = $zone;

			}

		}

		return $zones;

	}

	/**
	 * Format Block Data based on Input Field content
	 * @param array $blocksData
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException
	 */
	private function prepareBlocksData(Array $blocksData){

		$blocks = array();

		// get field configuration
		$blocksConfig = $this->getBlocks();

		foreach ($blocksData as $blockData) {

			if (isset($blockData['type']) && $blockData['type']
				&& isset($blockData['content']) && $blockData['content']
				&& isset($blockData['type']['identifier']) && $blockData['type']['identifier']
				&& isset($blocksConfig[$blockData['type']['identifier']]) && $blocksConfig[$blockData['type']['identifier']]
				&& isset($blocksConfig[$blockData['type']['identifier']]['data']) && $blocksConfig[$blockData['type']['identifier']]['data']) {

				// create config
				$config = new stdClass();

				$config->name = ($blocksConfig[$blockData['type']['identifier']]['data']['name'] ?? '');
				$config->identifier = ($blocksConfig[$blockData['type']['identifier']]['identifier'] ?? '');
				$config->type = ($blocksConfig[$blockData['type']['identifier']]['type'] ?? '');
				$config->description = ($blocksConfig[$blockData['type']['identifier']]['data']['description'] ?? '');
				$config->template = ($blocksConfig[$blockData['type']['identifier']]['data']['template'] ?? '');

				// get default template
				if ($this->container->hasParameter('mugopage_settings')) {
					$mugoPageSettings = $this->container->getParameter('mugopage_settings');
					if ($mugoPageSettings && array_key_exists('default_templates', $mugoPageSettings) && array_key_exists('block', $mugoPageSettings['default_templates'])) {
						$config->defaulttemplate = $mugoPageSettings['default_templates']['block'];
					}
				}

				$config->zones = ($blocksConfig[$blockData['type']['identifier']]['data']['zones'] ?? array());
				$config->attr = ($blocksConfig[$blockData['type']['identifier']]['data']['attr'] ?? array());;

				// object data
				$block = new stdClass();
				$block->name = $blockData['content']['name'];
				$block->identifier = $config->identifier;
				$block->id = $blockData['content']['id'];
				$block->type = $config->type;
				$block->config = $config;

				// get related content
				$relatedContentArray = array();
				if(isset($blockData['content']['related_content']) && $blockData['content']['related_content']) {

					foreach ($blockData['content']['related_content'] as $relatedContendId){
						try {

							$content = $this->contentService->loadContent($relatedContendId);

							$relatedContentArray[ $content->id ] = array(
								'name' => $content->getName(),
								'content_id' => $content->id,
								'location_id' => $content->contentInfo->mainLocationId,
								'identifier' => $content->getContentType()->identifier,
							);

						} catch (Exceptions\NotFoundException $e) {
							// do nothing
						} catch (Exceptions\UnauthorizedException $e) {
							// do nothing
						}
					}

				}
				$block->relatedcontent = $relatedContentArray;

				// get custom attributes
				$customAttributes = array();
				if($config->attr){

					// loop config objects
					foreach ($config->attr as $configAttribute){
                                                $customAttribute = [
							'name' => $configAttribute['name'],
							'identifier' => $configAttribute['identifier'],
							'type' => $configAttribute['type'],
							'value' => null,
						];

						// loop the data custom attribute to find value
						if (isset($blockData['custom_attributes']) && $blockData['custom_attributes']) {
							foreach ($blockData['custom_attributes'] as $dataAttribute) {

								// update value attribute when data and config identifiers match
								if ($dataAttribute['identifier'] == $configAttribute['identifier']) {
									switch ($configAttribute['type']) {
										case 'string':
										case 'integer':
                                                                                case 'text':
											$customAttribute['value'] = $dataAttribute['value'];
											break;
                                                                                case 'choice':
											$customAttribute['value'] = $dataAttribute['value'];
                                                                                        $customAttribute['options'] = $configAttribute['options'];
											break;
										case 'contentrelation':
                                                                                        $customAttribute['value'] = [];
											foreach ($dataAttribute['value'] as $relatedContendId){
												try {

													$content = $this->contentService->loadContent($relatedContendId);

													$customAttribute['value'][ $content->id ] = array(
														'name' => $content->getName(),
														'content_id' => $content->id,
														'location_id' => $content->contentInfo->mainLocationId,
														'identifier' => $content->getContentType()->identifier,
													);

												} catch (Exceptions\NotFoundException $e) {
													// do nothing
												} catch (Exceptions\UnauthorizedException $e) {
													// do nothing
												}
											}

											break;
									}

								}

							}
						}

						// add the custom attribute config
						$customAttributes[] = $customAttribute;

					}

				}

				$block->attr = $customAttributes;

				$blocks[$block->id] = $block;

			}

		}

		return $blocks;

	}

	public function getContentTypes() {

		$contentTypesList = array();

		// get list of groups to exclude from parameters.yml
		$excludeGroupsList = array();
		if ($this->container->hasParameter('mugopage_settings')) {
			$mugoPageSettings = $this->container->getParameter('mugopage_settings');
			if ($mugoPageSettings && array_key_exists('exclude_content_groups', $mugoPageSettings)) {
				$excludeGroupsList = $mugoPageSettings['exclude_content_groups'];
			}
		}

		// get content type groups
		$contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();
		foreach ($contentTypeGroups as $contentTypeGroup){

			// get the content types on each group
			$contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroup);
			$contentTypesData = array();

			if (!array_key_exists($contentTypeGroup->id, $excludeGroupsList)) {

				foreach ($contentTypes as $contentType){

					$contentTypesData[$contentType->id] = array(
						'id' => $contentType->id,
						'identifier' => $contentType->identifier,
						'name' => $contentType->getName()
					);

				}

				// sort content type by name
				usort($contentTypesData, function($a, $b) {
					return $a['name'] <=> $b['name'];
				});

				$contentTypesList[$contentTypeGroup->id] = array(
					'identifier' => $contentTypeGroup->identifier,
					'id' => $contentTypeGroup->id,
					'content_types' => $contentTypesData
				);

			}

		}

		return $contentTypesList;

	}

	/**
	 * Return a list of custom attribute type available
	 * See parameters:mugopage_settings:custom_attribute_types_available
	 * @return array
	 */
	public function getCustomAttributeTypes(){

		$customAttributeTypes = array();

		// get configuration from settings
		if ($this->container->hasParameter('mugopage_settings')) {
			$mugoPageSettings = $this->container->getParameter('mugopage_settings');
			if ($mugoPageSettings && array_key_exists('custom_attribute_types_available', $mugoPageSettings) ) {
				$customAttributeTypes = $mugoPageSettings['custom_attribute_types_available'];
			}
		}

		return $customAttributeTypes;

	}

}