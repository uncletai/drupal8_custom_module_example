<?php

namespace Drupal\pfapau_contact\Breadcrumb;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * For patient breadcrumbs builder.
 */
class ContactBreadcrumbBuilder implements BreadcrumbBuilderInterface
{

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
    protected $currentUser;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
    protected $accessManager;

  /**
   * {@inheritdoc}
   */
    public function __construct(
        AccountInterface $current_user,
        AccessManagerInterface $access_manager
    ) {
        $this->currentUser = $current_user;
        $this->accessManager = $access_manager;
    }

  /**
   * {@inheritdoc}
   */
    public function applies(RouteMatchInterface $route_match)
    {
        if (in_array($route_match->getRouteName(), [
        'contact_add',
        'contact_edit'
        ])) {
            return true;
        }

        return false;
    }

  /**
   * {@inheritdoc}
   */
    public function build(RouteMatchInterface $route_match)
    {
      // Define a new object of type Breadcrumb.
        $breadcrumb = new Breadcrumb();

      // Add cache.
        $access = $this->accessManager->check($route_match, $this->currentUser, null, true);
        $breadcrumb->addCacheableDependency($access);
        $breadcrumb->addCacheContexts(['url.path']);

      // Get links.
        $links = [];
        $links[] = Link::createFromRoute('Home', '<front>');
        $links[] = Link::createFromRoute('Contacts', 'contact_log_list');

        if ($route_match->getRouteName() == 'contact_add') {
            $links[] = Link::createFromRoute('Add contact', 'contact_add');
        } elseif ($route_match->getRouteName() == 'contact_edit') {
            $path = \Drupal::request()->getpathInfo();
            $arg = explode('/', $path);
            $links[] = Link::createFromRoute('Contact details', 'contact_edit', ['log_id' => $arg[2]]);
        }

        return $breadcrumb->setLinks($links);
    }
}
