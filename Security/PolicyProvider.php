<?php

namespace Mugo\PageBundle\Security;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ConfigBuilderInterface;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Security\PolicyProvider\PolicyProviderInterface;

class PolicyProvider implements PolicyProviderInterface
{
    public function addPolicies(ConfigBuilderInterface $configBuilder)
    {
        $configBuilder->addConfig([
			"mugopage_config" => [
				"read" => null,
			],
			"mugopage_config_layouts" => [
				"read" => null,
				"edit" => null,
				"delete" => null,
			],
			"mugopage_config_zones" => [
				"read" => null,
				"edit" => null,
				"delete" => null,
			],
			"mugopage_config_blocks" => [
				"read" => null,
				"edit" => null,
				"delete" => null,
			],
         ]);
    }
}