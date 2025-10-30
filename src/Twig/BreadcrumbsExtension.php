<?php
// src/Twig/BreadcrumbsExtension.php
namespace App\Twig;

use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadcrumbsExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $url,
        private ClientRepository $clients,
        private OrderRepository $orders,
    ) {}

    public function getFunctions(): array
    {
        // Utilise dans Twig: breadcrumbs(app.request)
        return [ new TwigFunction('breadcrumbs', [$this, 'build']) ];
    }

    /**
     * Retourne un tableau d’items:
     * [['label'=>'Dashboard','url'=>'/admin'], ['label'=>'Clients','url'=>'/admin/client'], ['label'=>'John','url'=>null]]
     */
    public function build(): array
    {
        $req   = $this->requestStack->getCurrentRequest();
        if (!$req) return [];

        $route = (string) $req->attributes->get('_route');
        $args  = (array) $req->attributes->get('_route_params', []);

        // toujours le point d’entrée
        $items = [
            ['label' => '🏠 Dashboard', 'url' => $this->url->generate('admin_home')],
        ];

        // REGISTRY minimal des routes → logique de trail
        switch ($route) {
            // Clients
            case 'app_client_index':
                $items[] = ['label' => 'Clients', 'url' => null];
                break;

            case 'app_client_new':
                $items[] = ['label' => 'Clients', 'url' => $this->url->generate('app_client_index')];
                $items[] = ['label' => 'New', 'url' => null];
                break;

            case 'app_client_show':
            case 'app_client_edit': {
                $items[] = ['label' => 'Clients', 'url' => $this->url->generate('app_client_index')];
                $id = isset($args['id']) ? (int)$args['id'] : 0;
                $name = 'Client #'.$id;
                if ($id > 0 && ($c = $this->clients->find($id))) {
                    $name = $c->getName() ?: $name;
                }
                if ($route === 'app_client_show') {
                    $items[] = ['label' => $name, 'url' => null];
                } else {
                    $items[] = ['label' => $name, 'url' => $this->url->generate('app_client_show', ['id'=>$id])];
                    $items[] = ['label' => 'Edit', 'url' => null];
                }
                break;
            }

            // Orders
            case 'app_order_index':
                $items[] = ['label' => 'Orders', 'url' => null];
                break;

            case 'app_order_new':
                $items[] = ['label' => 'Orders', 'url' => $this->url->generate('app_order_index')];
                $items[] = ['label' => 'New', 'url' => null];
                break;

            case 'app_order_show':
            case 'app_order_edit': {
                $items[] = ['label' => 'Orders', 'url' => $this->url->generate('app_order_index')];
                $id = isset($args['id']) ? (int)$args['id'] : 0;
                $title = 'Order #'.$id;
                if ($id > 0 && ($o = $this->orders->find($id))) {
                    $title = $o->getTitle() ?: $title;
                }
                if ($route === 'app_order_show') {
                    $items[] = ['label' => $title, 'url' => null];
                } else {
                    $items[] = ['label' => $title, 'url' => $this->url->generate('app_order_show', ['id'=>$id])];
                    $items[] = ['label' => 'Edit', 'url' => null];
                }
                break;
            }

            // TimeEntry (si nécessaire)
            case 'app_time_entry_index':
                $items[] = ['label' => 'Time entries', 'url' => null];
                break;

            default:
                // fallback léger: affiche le nom de route “humainisé”
                // Utile pour routes non mappées.
                $label = ucfirst(str_replace(['app_','admin_','_','-'], ['','',' ',' '], $route));
                $items[] = ['label' => $label, 'url' => null];
                break;
        }

        return $items;
    }
}
