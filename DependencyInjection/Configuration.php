<?php

namespace Innocead\CaptchaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree.
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('innocead_captcha', 'array');

        $fonts = array(
            'luggerbu.ttf',
            'elephant.ttf',
            'SCRAWL.ttf',
            'Alanden.ttf'
        );

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('width')->defaultValue(100)->end()
            ->scalarNode('height')->defaultValue(20)->end()
            ->scalarNode('char_max_size')->end()
            ->scalarNode('char_min_size')->end()
            ->scalarNode('char_transparent')->end()
            ->scalarNode('char_px_spacing')->end()
            ->booleanNode('char_random_color')->end()
            ->enumNode('char_random_color_lvl')->values(array(1, 2, 3, 4))->end()
            ->scalarNode('max_chars')->end()
            ->scalarNode('min_chars')->end()
            ->scalarNode('char_max_rot_angle')->end()
            ->scalarNode('chars_used')->end()
            ->arrayNode('char_fonts')
                ->addDefaultChildrenIfNoneSet()
                ->prototype('enum')->values($fonts)->defaultValue($fonts[0])->end()
            ->end()

            ->booleanNode('effect_greyscale')->end()
            ->booleanNode('effect_blur')->end()

            ->scalarNode('noise_min_px')->end()
            ->scalarNode('noise_max_px')->end()
            ->scalarNode('noise_min_lines')->end()
            ->scalarNode('noise_max_lines')->end()
            ->scalarNode('noise_min_circles')->end()
            ->scalarNode('noise_max_circles')->end()
            ->enumNode('noise_color')->values(array(1, 2, 3))->end()
            ->scalarNode('brush_size')->end()
            ->booleanNode('bg_transparent')->end()
            ->scalarNode('bg_red')->end()
            ->scalarNode('bg_green')->end()
            ->scalarNode('bg_blue')->end()
            ->scalarNode('bg_border')->end()
            ->scalarNode('noise_on_top')->end()

            ->scalarNode('flood_timer')->end()
            ->booleanNode('test_queries_flood')->end()
            ->scalarNode('max_refresh')->end()
            ->end()
        ;

        return $treeBuilder;
    }

}