<?php

/**
 * @file
 * Contains \Drupal\rest_menu\Plugin\rest\resource\rest_menu.
 */

namespace Drupal\rest_menu\Plugin\rest\resource;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Menu;
use Drupal\Core\MenuTreeParameters;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "restmenu_resource",
 *   label = @Translation("REST Menu Resource"),
 *   uri_paths = {
 *     "canonical" = "/entity/restmenu/{menu}"
 *   }
 * )
 */
class RESTMenuResource extends ResourceBase {
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user')
    );
  }
  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($entity) {
    $menu_name = $entity;
    $menu_parameters = \Drupal::menuTree()->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = \Drupal::menuTree()->load($menu_name, $menu_parameters);
    $result = array();

    foreach ($tree as $element) {
      if ($element->link->isEnabled()) {
        array_push($result, $this->createEntry($element));
      }
    }

    $response = new JsonResponse();
    $response->setData($result);

    return $response;
  }

  private function createEntry($element) {
    $link = $element->link;

    $the_url = '';
    if ($link->getUrlObject()->isExternal()) {
      $the_url = $link->getUrlObject()->getUri();
    } else {
      $the_url = $link->getUrlObject()->getInternalPath();
    }

    if ($element->hasChildren) {
      $children = array();
      $subtree = $element->subtree;

      foreach ($subtree as $subelement) {
        if ($subelement->link->isEnabled()) {
          array_push($children, $this->createEntry($subelement));
        }
      }

      if (!empty($children)) {
        return array(
          'title' => $link->getTitle(),
          'url' => $the_url,
          'weight' => $link->getWeight(),
          'children' => $children
        );

        //otherwise the below return statement is reached
      }
      
    }
    
    return array(
      'title' => $link->getTitle(),
      'url' => $the_url,
      'weight' => $link->getWeight()
    );
  }
}
