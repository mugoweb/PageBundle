<?php

namespace Mugo\PageBundle\Controller;

use Ibexa\Contracts\AdminUi\Controller\Controller;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpFoundation\Response;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Pagerfanta\Pagerfanta;
use \Ibexa\Contracts\Core\Repository\Exceptions;
use \Ibexa\Contracts\Core\Repository\PermissionResolver;

class ConfigController extends Controller
{

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
	 * @var \Ibexa\Contracts\Core\Repository\PermissionResolver
	 */
	private $permissionResolver;


    public function __construct($repository, $mugoPageService, $contentService, $permissionResolver)
    {
		$this->repository = $repository;
        $this->mugoPageService = $mugoPageService;
        $this->contentService = $contentService;
		$this->permissionResolver = $permissionResolver;
    }

    public function performAccessCheck(): void
    {
        parent::performAccessCheck();
        $this->denyAccessUnlessGranted(new Attribute('mugopage_platform', 'admin'));
    }

	/**
	 * Renders the MugoPage config Dashboard page
	 *
	 * @param String $template
	 * @return Response
	 */
    public function renderDashboardAction( String $template ): Response
	{

		// checks permission
		parent::performAccessCheck();
		$this->denyAccessUnlessGranted(
			new Attribute('mugopage_config', 'read')
		);

		$breadcrumbItems = array(
			array('value' => 'MugoPage Config Dashboard'),
		);

        $params = array(
            'blockTitle' => 'MugoPage Config Dashboard',
            'breadcrumbItems' => $breadcrumbItems
		);

		$request  = Request::createFromGlobals();
        return $this->render($template, $params );

    }

	/**
	 * Prepare content and render the layout config page
	 */
	public function renderLayoutsAction( String $template ): Response
	{

		// checks permission
		parent::performAccessCheck();
		$this->denyAccessUnlessGranted(
			new Attribute('mugopage_config_layouts', 'read')
		);

		$breadcrumbItems = array(
			array('value' => 'MugoPage Config Dashboard', 'url' => $this->generateUrl('mugopage_config.dashboard')),
			array('value' => 'Layouts'),
		);

		$zones = $this->mugoPageService->getZones();

		$contentTypesInGroups = $this->mugoPageService->getContentTypes();

		$layouts = $this->mugoPageService->getLayouts();

		$params = array(
			'blockTitle' => 'MugoPage Config Layouts',
			'breadcrumbItems' => $breadcrumbItems,
			'zones' => $zones,
			'contentTypesInGroups' => $contentTypesInGroups,
			'layouts' => $layouts
		);

		return $this->render($template, $params );

	}

	/**
	 * Prepare content and render the zone config page
	 */
	public function renderZonesAction( String $template ): Response
	{

		// checks permission
		parent::performAccessCheck();
		$this->denyAccessUnlessGranted(
			new Attribute('mugopage_config_zones', 'read')
		);

		$breadcrumbItems = array(
			array('value' => 'MugoPage Config Dashboard', 'url' => $this->generateUrl('mugopage_config.dashboard')),
			array('value' => 'Zones'),
		);

		$zones = $this->mugoPageService->getZones();

		$params = array(
			'blockTitle' => 'MugoPage Config Zones',
			'breadcrumbItems' => $breadcrumbItems,
			'zones' => $zones
		);

		return $this->render($template, $params );

	}

	/**
	 * Prepare content and render the block config page
	 */
	public function renderBlocksAction( String $template ): Response
	{

		// checks permission
		parent::performAccessCheck();
		$this->denyAccessUnlessGranted(
			new Attribute('mugopage_config_blocks', 'read')
		);

		$breadcrumbItems = array(
			array('value' => 'MugoPage Config Dashboard', 'url' => $this->generateUrl('mugopage_config.dashboard')),
			array('value' => 'Blocks'),
		);

		$zones = $this->mugoPageService->getZones();

		$contentTypesInGroups = $this->mugoPageService->getContentTypes();

		$customAttributeTypes = $this->mugoPageService->getCustomAttributeTypes();

		$blocks = $this->mugoPageService->getBlocks();

		$params = array(
			'blockTitle' => 'MugoPage Config Blocks',
			'breadcrumbItems' => $breadcrumbItems,
			'zones' => $zones,
			'contentTypesInGroups' => $contentTypesInGroups,
			'blocks' => $blocks,
			'customAttributeTypes' => $customAttributeTypes,
		);

		return $this->render($template, $params );

	}

	/**
	 * Entry point for saving layout action
	 * @return JsonResponse
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function saveLayoutAction(): JsonResponse
	{
		$canEdit = $this->permissionResolver->hasAccess('mugopage_config_layouts', 'edit');
		if($canEdit){

			$request = Request::createFromGlobals();
			if( $request->request->get('identifier', false) !== false ) {
				$jsonResponse = $this->mugoPageService->saveMugoPageConfiguration($this->mugoPageService::ITEM_TYPE_LAYOUT, $request);
			} else {
				$jsonResponse = array(
					'type' => 'warning',
					'message' => 'Identifier field is empty',
				);

			}

		} else {
			$jsonResponse = array(
				'type' => 'warning',
				'message' => 'You do not have permissions to create or edit layouts!',
			);
		}

		$response = new JsonResponse();
		$response->setData( $jsonResponse );
		return $response;
	}

	/**
	 * Entry point for saving zone action
	 * @return JsonResponse
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function saveZoneAction(): JsonResponse
	{

		$canEdit = $this->permissionResolver->hasAccess('mugopage_config_zones', 'edit');
		if($canEdit){

			$request = Request::createFromGlobals();
			if( $request->request->get('identifier', false) !== false ) {
				$jsonResponse = $this->mugoPageService->saveMugoPageConfiguration($this->mugoPageService::ITEM_TYPE_ZONE, $request);
			} else {
				$jsonResponse = array(
					'type' => 'warning',
					'message' => 'Identifier field is empty',
				);

			}

		} else {
			$jsonResponse = array(
				'type' => 'warning',
				'message' => 'You do not have permissions to create or edit zones!',
			);
		}

		$response = new JsonResponse();
		$response->setData( $jsonResponse );
		return $response;

	}

	/**
	 * Entry point for saving block action
	 * @return JsonResponse
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function saveBlockAction(): JsonResponse
	{
		$canEdit = $this->permissionResolver->hasAccess('mugopage_config_blocks', 'edit');
		if($canEdit){

			$request = Request::createFromGlobals();
			if( $request->request->get('identifier', false) !== false ) {
				$jsonResponse = $this->mugoPageService->saveMugoPageConfiguration($this->mugoPageService::ITEM_TYPE_BLOCK, $request);
			} else {
				$jsonResponse = array(
					'type' => 'warning',
					'message' => 'Identifier field is empty',
				);

			}

		} else {
			$jsonResponse = array(
				'type' => 'warning',
				'message' => 'You do not have permissions to create or edit blocks!',
			);
		}

		$response = new JsonResponse();
		$response->setData( $jsonResponse );
		return $response;
	}

	/**
	 * Entry point to delete items
	 * it requires $type and $identifier
	 * @return JsonResponse
	 */
	public function deleteAction(): JsonResponse
	{
		$request = Request::createFromGlobals();

		$type = $request->request->get('itemtype', false);
		$identifier = $request->request->get('identifier', false);

		$jsonResponse =  array();

		if ($type === false || $identifier === false){
			$jsonResponse =  array(
				'type' => 'warning',
				'message' => 'Your request does not have all the required parameters',
			);
		} else {

			$policyName = false;
			switch ($type) {
				case $this->mugoPageService::ITEM_TYPE_ZONE:
					$policyName = 'mugopage_config_zones';
					break;
				case $this->mugoPageService::ITEM_TYPE_LAYOUT:
					$policyName = 'mugopage_config_layouts';
					break;
				case $this->mugoPageService::ITEM_TYPE_BLOCK:
					$policyName = 'mugopage_config_blocks';
					break;
				default :
					$jsonResponse =  array(
						'type' => 'warning',
						'message' => 'Request type not found!',
					);
			}

			if ($policyName){

				// check permission
				$canDelete = $this->permissionResolver->hasAccess($policyName, 'delete');

				if($canDelete){

					$jsonResponse = $this->mugoPageService->deleteMugoPageItem($type, $identifier);

				} else {
					$jsonResponse = array(
						'type' => 'warning',
						'message' => 'You do not have permissions to delete items!',
					);
				}

			}

		}

		$response = new JsonResponse();
		$response->setData( $jsonResponse );
		return $response;

	}

    public function getRelatedItemsDataAction()
    {
        $request  = Request::createFromGlobals();
        $result = [];
        $relatedContentId = (int)$request->get('relatedContentId', 0);
        if($relatedContentId !== 0)
        {
            try {
                $content = $this->contentService->loadContent($relatedContentId);
                $result[$relatedContentId] = [
                    'contentId' => $relatedContentId,
                    'locationId' => $content->versionInfo->contentInfo->mainLocation->id,
                    'name' => $content->versionInfo->contentInfo->name];
            } catch (Exceptions\NotFoundException $e) {
               // do nothing
            } catch (Exceptions\UnauthorizedException $e) {
                // do nothing
            }

        }
        else
        {
            $contentId = (int)$request->get('contentId', 0);
            $content = false;
            try {
                $content = $this->contentService->loadContent($contentId);
            } catch (Exceptions\NotFoundException $e) {
               // do nothing
            } catch (Exceptions\UnauthorizedException $e) {
                // do nothing
            }

            if($content)
            {
                $relations = $this->contentService->loadRelations( $content->versionInfo );
                // store in a array a list of content ids of the reverse related objects
                foreach ($relations as $relation)
                {
                    $destinationInfo = $relation->getDestinationContentInfo();
                    if( $destinationInfo->id )
                    {
                        $result[$destinationInfo->id] = [
                            'contentId' => $destinationInfo->id,
                            'locationId' => $destinationInfo->mainLocation->id,
                            'name' => $destinationInfo->name];
                    }
                }
            }
        }

        $response = new JsonResponse( $result );
        $response->setPrivate();
        $response->setMaxAge( 0 );
        return$response;
    }

    public function blocksAction()
    {
        $this->performAccessCheck();
        $pathItems = [
            ['value' => 'MugoPage Platform', 'url' => $this->generateUrl('mugopage_platform.home') ],
            ['value' => 'Blocks' ],
        ];
        $params = [
            'title' => 'MugoPage Platform: Blocks',
            'path_items' => $pathItems
        ];
        $request = Request::createFromGlobals();
        if( $request->request->get('Publish', false) !== false )
        {
            $this->mugoPageService->updateConfiguration('mugopage', 'mugopage_blocks', $request->request->get('mugopage_blocks_value'));
        }
        $params['mugopage_blocks_value'] = $this->mugoPageService->getConfiguration('mugopage', 'mugopage_blocks');
        $zones = @json_decode($this->mugoPageService->getConfiguration('mugopage', 'mugopage_zones'), true);
        $params['zones'] = [];
        if($zones)
        {
            foreach($zones as $zone)
            {
                $params['zones'][] = [$zone['identifier'], $zone['name']];
            }
        }
        $params['contentTypes'] = $this->pageService->getContentTypes();

        return $this->render('@ibexadesign/mugopage_platform/pages/blocks.html.twig', $params );
    }



    /**
     * Checks if $parameterName is defined
     *
     * @param string $parameterName
     *
     * @return boolean
     */
    public function hasParameter( $parameterName )
    {
        return $this->get( 'ibexa.config.resolver' )->hasParameter( $parameterName );
    }
    /**
     * Returns value for $parameterName and fallbacks to $defaultValue if not defined
     *
     * @param string $parameterName
     * @param mixed $defaultValue
     *
     * @return mixed
     */
    public function getParameter( $parameterName, $defaultValue = null, $namespace = null )
    {
        if( $this->get( 'ibexa.config.resolver' )->hasParameter( $parameterName, $namespace ) )
        {
            return $this->get( 'ibexa.config.resolver' )->getParameter( $parameterName, $namespace );
        }
        return $defaultValue;
    }
    /**
     * Builds a response object
     *
     * @return Response Returns a Response object
     */
    protected function buildJSONResponse( $data )
    {
        $request  = Request::createFromGlobals();
        $response = new JsonResponse( $data );
        if ($this->getParameter('content.ttl_cache') === true)
        {
            $response->setSharedMaxAge(
                $this->getParameter('content.default_ttl')
            );
            $response->setExpires(
                    new \DateTime( "+" . $this->getParameter('content.default_ttl') . " seconds" )
            );
        }
        // Make the response vary against X-User-Hash header ensures that an HTTP
        // reverse proxy caches the different possible variations of the
        // response as it can depend on user role for instance.
        if ($request->headers->has('X-User-Hash'))
        {
            $response->setVary('X-User-Hash');
        }
        return $response;
    }

}