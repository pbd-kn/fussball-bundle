<?php


namespace PBDKN\FussballBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use PBDKN\FussballBundle\FussballBundle;
/**
 * Plugin for the Contao Manager.
 *
 * @author Peter Brogh
 */

// classname wird in composer unter extra festgelegt
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
echo "PBD FussballBundle Plugin FussballBundle getBundles\n";
        return [
            BundleConfig::create(FussballBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
                ->setReplace(['fussball']),                
        ];
    }
    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
echo "PBD FussballBundle Plugin FussballBundle getRouteCollection realpath Resources ". realpath(__DIR__.'/../Resources/config/routes.yaml')."\n";
        return $resolver
            ->resolve(__DIR__.'/../Resources/config/routes.yaml')
            ->load(__DIR__.'/../Resources/config/routes.yaml')
        ;
    }
    
}
