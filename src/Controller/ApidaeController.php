<?php

namespace Drupal\apidae_drupal_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Sitra\ApiClient\Exception\SitraException;
use Sitra\ApiClient\SitraServiceClient;
use Symfony\Component\HttpFoundation\Response;

class ApidaeController extends ControllerBase {

  const BATCH_SIZE = 50;
  const MAX_CYCLES = 20;

  public function import() {
    \Drupal::logger('Apidae')->info('Importing Apidae data - v1.1');

    $apidaeConfig = $this->config('system.apidae');
    $apiUrl = $apidaeConfig->get('api.url');
    $apiKey = $apidaeConfig->get('api.key');
    $apiProject = $apidaeConfig->get('api.project');
    $selections =  $apidaeConfig->get('data.selections');
    $objectsTypes =  array_filter($apidaeConfig->get('data.types'));
    $typesCriteria = join(" ", array_map(function($t) { return "type:".$t; }, $objectsTypes));
    $selections_ids = array_map('intval', explode(',', (string)$selections));

    $client = $this->createClient($apiUrl, $apiKey, $apiProject);

    \Drupal::logger('Apidae')->info('Client created');

    if($client) {
      foreach ($selections_ids as $selection) {
        $refreshMode = $this->config('system.apidae')->get('cron.type');
        $objectsCount = 0;
        $cycles = 0;
        $all_objects = [];

        try {
          $results = $this->loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria, $objectsCount);
          if (isset($results['objetsTouristiques'])) {
            $i = count($results['objetsTouristiques']);
            $objectsCount += $i;
            $cycles += 1;
            $all_objects += $results['objetsTouristiques'];
          }
          \Drupal::logger('Apidae query')->info("Selection ".$selection." - cycle ".$cycles." - ". $objectsCount ." objects - ".count($all_objects)." total");

          while($objectsCount < $results['numFound'] && $cycles < self::MAX_CYCLES) {
            $results = $this->loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria, $objectsCount);
            if (isset($results['objetsTouristiques'])) {
              $i = count($results['objetsTouristiques']);
              $objectsCount += $i;
              $cycles += 1;
              $all_objects += $results['objetsTouristiques'];
            }
            \Drupal::logger('Apidae query')->info("Selection ".$selection." - cycle ".$cycles." - ". $objectsCount ." objects - ".count($all_objects)." total");
          }

          foreach ($all_objects as $touristic_object) {
            $this->createNode($touristic_object, $selection, $refreshMode);
          }
        } catch(SitraException $e) {
          \Drupal::logger('Apidae module')->error('An error occurred during the retrieval of Apidae data. Please make sure that all configuration values have been properly set.');
          \Drupal::logger('Apidae module')->error($e->getMessage());
        }
        \Drupal::logger('Apidae module')->info('%d objects updated/created successfully', array('%d' => $objectsCount));
      }
    }

    return new Response('', 204);
  }

  private function loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria, $offset) {
    $results =  $client->searchObject([
      'query' => [
        "apiKey" => $apiKey,
        "projetId" => $apiProject,
        "first" => $offset,
        "selectionIds" => [$selection],
        "criteresQuery" => $typesCriteria,
        "responseFields" => ["id", "nom", "illustrations", "multimedias", "informations", "presentation",
          "localisation", "@informationsObjetTouristique", "ouverture.periodeEnClair",
          "ouverture.periodesOuvertures", "descriptionTarif.tarifsEnClair.LibelleFr", "contacts"]
      ]
    ]);
    \Drupal::logger('Apidae query')->info("Retrieved objects ".$offset." to ".($offset + count($results['objetsTouristiques']))." in total of ".$results['numFound']);

    return $results;
  }

  private function createClient($url, $key, $id) {
    try {
      $client = new SitraServiceClient([
        'baseUri' => $url,
        'apiKey' => $key,
        'projectId' => $id,
        'count' => self::BATCH_SIZE,
      ]);

      return $client;
    } catch (SitraException $e) {
      \Drupal::logger('Apidae module')->error('An error occurred during the connection setup with Apidae. Please make sure that all configuration values have been properly set.');
      \Drupal::logger('Apidae module')->error($e->getMessage());
      return null;
    }
  }

  private function checkNodeExists($id) {
    $result = null;
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'apidae_object', '=')
      ->condition('ao_id', $id, '=')
      ->execute();
    \Drupal::logger('check node')->info("check node exists for ".$id." : ".print_r($nids, true));

    if(count($nids) > 0) {
      $result = array_values($nids)[0];
    }
    return $result;
  }

  private function createApidaeObject() {
    $node = Node::create([
      'type' => 'apidae_object',
      'language' => 'fr',
      'status' => 1,
      'sticky' => 0,
      'promote' => 0,
      'comment' => 0,
      'created' => time(),
      'updated' => time()
    ]);
    return $node;
  }

  private function createNode($content, $selection, $refreshMode)
  {
    $contentId = $content['id'];
    $nid = $this->checkNodeExists($contentId);

    if (is_null($nid) || $refreshMode == 'new_content_and_updates') {

      if (is_null($nid)) {
        \Drupal::logger('Apidae')->info('node missing');
        $node = $this->createApidaeObject();
      } else {
        \Drupal::logger('Apidae')->info('updating existing node '.$nid);
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      }
      if(!is_null($node)) {
        if (isset($content['presentation']['descriptifDetaille']['libelleFr'])) {
          $complaint_body = $content['presentation']['descriptifDetaille']['libelleFr'];
        } elseif (isset($content['presentation']['descriptifCourt']['libelleFr'])) {
          $complaint_body = $content['presentation']['descriptifCourt']['libelleFr'];
        } else {
          $complaint_body = '';
        }

        $node->set('ao_id', $contentId);
        $node->setTitle($content['nom']['libelleFr']);
        $node->set('body', array(
          'value' => nl2br($complaint_body),
          'format' => 'full_html',
          'summary' => isset($content['presentation']['descriptifCourt']['libelleFr']) ? $content['presentation']['descriptifCourt']['libelleFr'] : text_summary(nl2br($complaint_body))
        ));
        $node->set('ao_type', $content['type']);

        // todo : setup yaml-based taxonomy (see https://www.metaltoad.com/blog/drupal-8-migrations-part-3-migrating-taxonomies-drupal-7)
        //      $type = $content['type'];
        //      $tid = _get_tid_from_type($type);
        //      $node->ao_type['und'][0] = array('tid' => $tid);

        // matched selections
        $selectionKey = "(".$selection.")";
        $selections = $node->ao_selections->value;
        $selections = isset($selections) ? explode(',', $selections) : array();

        if(!in_array($selectionKey, $selections)) {
          array_push($selections, $selectionKey);
          $node->set('ao_selections', join(',', $selections));
        }

        // location data
        if (isset($content['localisation']['adresse']['adresse1'])) {
          $node->set('ao_address1', $content['localisation']['adresse']['adresse1']);
        }
        if (isset($content['localisation']['adresse']['adresse2'])) {
          $node->set('ao_address2', $content['localisation']['adresse']['adresse2']);
        }
        if (isset($content['localisation']['adresse']['adresse3'])) {
          $node->set('ao_address3', $content['localisation']['adresse']['adresse3']);
        }
        if (isset($content['localisation']['adresse']['codePostal'])) {
          $node->set('ao_postal_code', $content['localisation']['adresse']['codePostal']);
        }
        if (isset($content['localisation']['adresse']['commune']['nom'])) {
          $node->set('ao_town', $content['localisation']['adresse']['commune']['nom']);
        }
        if (isset($content['localisation']['geolocalisation']['geoJson']['coordinates']['0'])) {
          $node->set('ao_latitude', $content['localisation']['geolocalisation']['geoJson']['coordinates']['1']);
        }
        if (isset($content['localisation']['geolocalisation']['geoJson']['coordinates']['1'])) {
          $node->set('ao_longitude', $content['localisation']['geolocalisation']['geoJson']['coordinates']['0']);
        }

        // contact info
        if (isset($content['informations']['moyensCommunication'])) {
          foreach ($content['informations']['moyensCommunication'] as $key => $value) {
            switch ($value['type']['id']) {
              case '201' :
                $node->set('ao_telephone', $value['coordonnees']['fr']);
                break;
              case '204' :
                $node->set('ao_email', $value['coordonnees']['fr']);
                break;
              case '205' :
                $node->set('ao_website', $value['coordonnees']['fr']);
                break;
              case '207' :
                $node->set('ao_facebook', $value['coordonnees']['fr']);
                break;
              default :
                break;
            }
          }
        }

        if (isset($content['presentation']['descriptifCourt']['libelleFr'])) {
          $node->set('ao_short_desc', nl2br($content['presentation']['descriptifCourt']['libelleFr']));
        }

        // first picture fields
        if (isset($content['illustrations'][0]['traductionFichiers'][0]['url'])) {
          $node->set('ao_pic1_large', $content['illustrations'][0]['traductionFichiers'][0]['url']);
        }
        if (isset($content['illustrations'][0]['traductionFichiers'][0]['urlDiaporama'])) {
          $node->set('ao_pic1_medium', $content['illustrations'][0]['traductionFichiers'][0]['urlDiaporama']);
        }
        if (isset($content['illustrations'][0]['nom']['libelleFr'])) {
          $node->set('ao_pic1_title', $content['illustrations'][0]['nom']['libelleFr']);
        }
        if (isset($content['illustrations'][0]['copyright']['libelleFr'])) {
          $node->set('ao_pic1_credits', $content['illustrations'][0]['copyright']['libelleFr']);
        }

        if (isset($content['multimedias'])) {
          foreach ($content['multimedias'] as $key => $value) {
            switch ($value['type']) {
              case 'VIDEO' :
                $node->set('ao_video_url', $value['traductionFichiers']['0']['url']);
                if (isset($value['nom']['libelleFr'])) {
                  $node->set('ao_video_title', $value['nom']['libelleFr']);
                }
                break;
              case 'DOCUMENT' :
                if (strpos($value['traductionFichiers'][0]['url'], 'pdf')) {
                  $node->set('ao_pdf_url', $value['traductionFichiers'][0]['url']);
                  if (isset($value['nom']['libelleFr'])) {
                    $node->set('ao_pdf_title', $value['nom']['libelleFr']);
                  }
                  break;
                }
            }
          }
        }

        if (isset($content['ouverture']['periodeEnClair']['libelleFr'])) {
          $node->set('ao_openings', $content['ouverture']['periodeEnClair']['libelleFr']);
        }

        if (isset($content['descriptionTarif']['tarifsEnClair']['libelleFr'])) {
          $node->set('ao_rates', $content['descriptionTarif']['tarifsEnClair']['libelleFr']);
        }

        $node->save();
      } else {
        if (!is_null($nid)) {
          \Drupal::logger('Apidae')->warning('Could not retrieve '.$nid);
        }
      }
    }
  }
}