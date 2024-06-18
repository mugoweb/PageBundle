<?php

namespace Mugo\PageBundle\Twig;

use stdClass;
use Doctrine\DBAL\Exception as ExceptionAlias;
use \Ibexa\Contracts\Core\Repository\ContentService;
use \Ibexa\Contracts\Core\Repository\Exceptions;
use \Ibexa\Contracts\Core\Repository\Repository;
use \Mugo\PageBundle\Services\MugoPageService;
use \Symfony\Component\DependencyInjection\Container;
use \Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use \Twig\Extension\AbstractExtension;
use \Twig\TwigFunction;
use \Twig\Environment;

class Extension extends AbstractExtension
{

	/**
	 * @var \Symfony\Component\DependencyInjection\Container
	 */
	private $container;

	/**
	 * @var \Ibexa\Contracts\Core\Repository\Repository
	 */
	private $repository;

	/**
	 * @var \Mugo\PageBundle\Services\MugoPageService
	 */
	private $mugoPageService;

	/**
	 * @var \Ibexa\Contracts\Core\Repository\ContentService
	 */
	private $contentService;

	/**
	 * @var \Twig\Environment
	 */
	private $twigEnvironment;

    public function __construct(
		Container $container,
		Repository $repository,
		MugoPageService $mugoPageService,
		ContentService $contentService,
		Environment $twigEnvironment
	){
        $this->container = $container;
		$this->repository = $repository;
		$this->mugoPageService = $mugoPageService;
        $this->contentService = $contentService;
		$this->twigEnvironment = $twigEnvironment;
    }

	/**
	 * Get name extension
	 * @return string
	 */
    public function getName(){
        return 'mugo_page_extension';
    }

    /**
     * Registers new twig functions
     *
     * @return array with the new twig functions
     */
    public function getFunctions()
    {
        return array(
			new TwigFunction('get_field_content_object', array($this, 'getFieldContentObject')),
			new TwigFunction('get_mugopage_layouts', array($this, 'getMugoPageLayouts')),
            new TwigFunction('get_mugopage_zones', array($this, 'getMugoPageZones')),
			new TwigFunction('get_mugopage_blocks', array($this, 'getMugoPageBlocks')),
			new TwigFunction('get_related_content', array($this, 'getRelatedContent')),
			new TwigFunction('parse_stdclass_to_array', array($this, 'parseStdClassToArray')),
            new TwigFunction('render_mugopage_layout', array($this, 'renderMugoPageLayout'), ['is_safe' => ['html']]),
            new TwigFunction('render_mugopage_zone', array($this, 'renderMugoPageZone'), ['is_safe' => ['html']]),
            new TwigFunction('render_mugopage_block', array($this, 'renderMugoPageBlock'), ['is_safe' => ['html']]),
			new TwigFunction('get_allowed_content_identifiers', array($this, 'getAllowedContentIdentifiers')),
        );
    }

	/**
	 * Convert field String into an object
	 * @return array
	 */
	function getFieldContentObject(String $value){

		$fieldContentObject = $this->mugoPageService->getFieldContentObject($value);
		return $fieldContentObject;

	}


	/**
	 * Return all the layouts available
	 */
	function getMugoPageLayouts(){
		$layouts = $this->mugoPageService->getLayouts();
		return $layouts;
	}

	/**
	 * Return all blocks available
	 */
    function getMugoPageBlocks()
    {
		$blocks = $this->mugoPageService->getBlocks();
		return $blocks;
    }

	/**
	 * Return all zones available
	 */
    function getMugoPageZones(){
		$zones = $this->mugoPageService->getZones();
		return $zones;
    }

	/**
	 * Get the related content from a value field
	 * @param $value
	 * @param $useDefaultTemplate
	 * @return null
	 * @throws \Exception
	 */
	function getRelatedContent($value){

		$relatedContentArray = array();

		// convert value into an object
		$valueObj = @json_decode($value, true);

		// loop zones
		if (isset($valueObj['zones']) && $valueObj['zones']){

			$relatedContendIds = array();

			foreach ($valueObj['zones'] as $zone){

				// loop blocks
				if (isset($zone['blocks']) && $zone['blocks']) {
					foreach ($zone['blocks'] as $block){

						// loop custom attribute with relations
						if (isset($block['custom_attributes']) && $block['custom_attributes']){

							foreach ($block['custom_attributes'] as $custom_attribute){
								if ($custom_attribute['type'] == 'contentrelation'){
									foreach ($custom_attribute['value'] as $relatedItem){
										$relatedContendIds[(int)$relatedItem] = $relatedItem;
									}
								}
							}

						}

					}
				}

			}

			// fetch the content
			if ($relatedContendIds){

				foreach ($relatedContendIds as $relatedContendId){
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

		}

		return $relatedContentArray;

	}

	/**
	 * Convert stdClass object in array
	 * That is useful to look all the content and configuration inside an object
	 *
	 * @param stdClass $object
	 * @return mixed
	 */
	function parseStdClassToArray(stdClass $object){
		return json_decode(json_encode($object), true);
	}

	/**
	 * Renders a layout template
	 *
	 * @param $fieldValue | It can be a string or a stdClass from mugoPageService->getFieldContentObject
	 * @param array $parameters
	 * @param bool $useDefaultTemplate
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    function renderMugoPageLayout($fieldValue, Array $parameters, Bool $useDefaultTemplate = false) {

		$renderedTemplate =  null;

		// convert string into mugoPage std object
		// this means this function can also receive a mugoPage object as parameter
		if (is_string($fieldValue)){
			$fieldValue = $this->mugoPageService->getFieldContentObject($fieldValue);
		}

		if (isset($fieldValue->layout) && $fieldValue->layout){

			$layout = $fieldValue->layout;

			$layoutTemplate = '@' . $layout->config->defaulttemplate;
			if (!$useDefaultTemplate){
				$layoutTemplate =  '@' . $layout->config->template;
			}

			$renderedTemplate = $this->twigEnvironment->render(
				$layoutTemplate, [
				'layout' => $layout,
				'zones' => $layout->zones,
				'parameters' => $parameters,
			]);

		}

        return $renderedTemplate;
    }

	/**
	 * Renders a zone template
	 *
	 * @param stdClass $zoneObj | It must be a mugoPage stdClass object
	 * @param array $parameters
	 * @param bool $useDefaultTemplate
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    function renderMugoPageZone(stdClass $zoneObj, Array $parameters = [], Bool $useDefaultTemplate = false){

		$renderedTemplate =  null;

		if (isset($zoneObj->config->defaulttemplate) && $zoneObj->config->defaulttemplate){

			$zoneTemplate = '@' . $zoneObj->config->defaulttemplate;
			if (!$useDefaultTemplate){
				// NEEDS WORK
				// create a template field in zones configuration and allow editors
				// to specify a custom template for zones
				//$zoneTemplate =  '@' . $zoneObj->config->template;
			}

			$renderedTemplate = $this->twigEnvironment->render(
				$zoneTemplate, [
				'zone' => $zoneObj,
				'blocks' => $zoneObj->blocks,
				'parameters' => $parameters,
			]);
		}

		return $renderedTemplate;

    }

	/**
	 * Renders a zone template
	 *
	 * @param stdClass $blockObj | It must be a mugoPage stdClass object
	 * @param array $parameters
	 * @param bool $useDefaultTemplate
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    function renderMugoPageBlock(stdClass $blockObj, Array $parameters = [], Bool $useDefaultTemplate = false) {

		$renderedTemplate =  null;

		if (isset($blockObj->config->defaulttemplate) && $blockObj->config->defaulttemplate){

			$blockTemplate = '@' . $blockObj->config->defaulttemplate;
			if (!$useDefaultTemplate){
				$blockTemplate =  '@' . $blockObj->config->template;
			}

			$renderedTemplate = $this->twigEnvironment->render(
				$blockTemplate, [
				'block' => $blockObj,
				'parameters' => $parameters,
			]);
		}

		return $renderedTemplate;

    }

	/**
	 * Convert a list of content ids into a list of content identifiers
	 * @param array $allowedContentIds
	 * @return array
	 */
	function getAllowedContentIdentifiers(array $allowedContentIds) {

		$contentGroups = $this->mugoPageService->getContentTypes();

		$allowedIdentifiers = array();

		foreach ($contentGroups as $contentGroup){

			if($contentGroup['content_types']){
				foreach ($contentGroup['content_types'] as $contentType){

					if (in_array($contentType['id'], $allowedContentIds ) ){
						$allowedIdentifiers[] = $contentType['identifier'];
					}

				}
			}

		}

		return $allowedIdentifiers;

	}

}
