<?php

namespace Mugo\PageBundle\EventListener;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MainMenuBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;

final class MenuListener implements EventSubscriberInterface
{
    /**
     * @var \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }
    public static function getSubscribedEvents() : array
    {
        return [ConfigureMenuEvent::MAIN_MENU => 'onMainMenuBuild'];
    }

    public function onMainMenuBuild(ConfigureMenuEvent $event): void
    {

        $menu = $event->getMenu();

		$canAccessMugoPage = $this->authorizationChecker->isGranted(
			new Attribute('mugopage_config', 'read')
		);

		if ($canAccessMugoPage) {

			$contentMugoPage = $menu[MainMenuBuilder::ITEM_ADMIN]->addChild(
				'mugopage_config',
				[
					'label' => 'MugoPage Config',
					'extras' => [
						'orderNumber' => 100,
					],
				],
			);

			$contentMugoPage->addChild(
				'mugopage_config_dashboard',
				[
					'label' => 'Dashboard',
					'route' => 'mugopage_config.dashboard',
					'extras' => [
						'orderNumber' => 1,
					],
				]
			);

			$contentMugoPage->addChild(
				'mugopage_config_layouts',
				[
					'label' => 'Layouts',
					'route' => 'mugopage_config.layouts',
					'extras' => [
						'orderNumber' => 2,
					],
				]
			);

			$contentMugoPage->addChild(
				'mugopage_config_zones',
				[
					'label' => 'Zones',
					'route' => 'mugopage_config.zones',
					'extras' => [
						'orderNumber' => 3,
					],
				]
			);

			$contentMugoPage->addChild(
				'mugopage_config_blocks',
				[
					'label' => 'Blocks',
					'route' => 'mugopage_config.blocks',
					'extras' => [
						'orderNumber' => 4,
					],
				]
			);

		}

    }
}