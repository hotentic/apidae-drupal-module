<?php

namespace Drupal\apidae_drupal_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Sitra\ApiClient\Exception\SitraException;
use Sitra\ApiClient\Client;
use Symfony\Component\HttpFoundation\Response;

class ApidaeController extends ControllerBase
{

    const BATCH_SIZE = 50;
    const MAX_CYCLES = 20;

    public function import()
    {
        \Drupal::logger('Apidae')->info('Importing Apidae data');

        $apidaeConfig = $this->config('system.apidae');
        $apiUrl = $apidaeConfig->get('api.url');
        $apiKey = $apidaeConfig->get('api.key');
        $apiProject = $apidaeConfig->get('api.project');
        $selections = $apidaeConfig->get('data.selections');
        $objectsTypes = array_filter($apidaeConfig->get('data.types'));
        $typesCriteria = join(" ", array_map(function ($t) {
            return "type:" . $t;
        }, $objectsTypes));
        $selections_ids = array_map('intval', explode(',', (string)$selections));

        $client = $this->createClient($apiUrl, $apiKey, $apiProject);

        \Drupal::logger('Apidae')->info('Client created');

        if ($client) {
            foreach ($selections_ids as $selection) {
                $refreshMode = $this->config('system.apidae')->get('cron.type');
                $objectsCount = 0;
                $cycles = 0;
                $all_objects = [];
                $results = array();

                try {
                    while (($cycles == 0 || $objectsCount < $results['numFound']) && $cycles < self::MAX_CYCLES) {
                        $cycles += 1;
                        $results = $this->loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria, $objectsCount);
                        if (isset($results['objetsTouristiques'])) {
                            $objectsCount += count($results['objetsTouristiques']);
                            $all_objects = array_merge($all_objects, array_values($results['objetsTouristiques']));
                        }
                    }
                    \Drupal::logger('Apidae query')->info("Selection " . $selection . " - Retrieved " . count($all_objects) . " objects");

                    foreach ($all_objects as $touristic_object) {
                        $this->createNode($touristic_object, $selection, $refreshMode);
                    }
                    \Drupal::logger('Apidae module')->info('%d objects updated/created successfully', array('%d' => $objectsCount));

                } catch (SitraException $e) {
                    \Drupal::logger('Apidae module')->error('An error occurred during the retrieval of Apidae data. Please make sure that all configuration values have been properly set.');
                    \Drupal::logger('Apidae module')->error($e->getMessage());
                }
            }
        }

        return new Response('', 204);
    }

    private function loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria, $offset)
    {
        $results = $client->searchObject([
            'query' => [
                "apiKey" => $apiKey,
                "projetId" => $apiProject,
                "first" => $offset,
                "selectionIds" => [$selection],
                "criteresQuery" => $typesCriteria,
                "responseFields" => ["id", "nom", "illustrations", "multimedias", "informations", "presentation",
                    "localisation", "@informationsObjetTouristique", "ouverture.periodeEnClair",
                    "ouverture.periodesOuvertures", "descriptionTarif.tarifsEnClair.libelleFr", "contacts", "liens",
                    "donneesPrivees", "criteresInternes", 'prestations']
            ]
        ]);
        \Drupal::logger('Apidae query')->info("Retrieved " . count($results['objetsTouristiques']) . " objects starting from " . $offset . " for a total of " . $results['numFound']);

        return $results;
    }

    private function createClient($url, $key, $id)
    {
        try {
            $client = new Client([
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

    private function checkNodeExists($id)
    {
        $result = null;
        $nids = \Drupal::entityQuery('node')
            ->condition('type', 'apidae_object', '=')
            ->condition('ao_id', $id, '=')
            ->execute();

        if (count($nids) > 0) {
            $result = array_values($nids)[0];
        }
        return $result;
    }

    private function createApidaeObject()
    {
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
                $node = $this->createApidaeObject();
            } else {
                $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
            }
            if (!is_null($node)) {
                if (isset($content['presentation']['descriptifDetaille']['libelleFr'])) {
                    $desc_body = $content['presentation']['descriptifDetaille']['libelleFr'];
                } elseif (isset($content['presentation']['descriptifCourt']['libelleFr'])) {
                    $desc_body = $content['presentation']['descriptifCourt']['libelleFr'];
                } else {
                    $desc_body = '';
                }

                $node->set('ao_id', $contentId);
                $node->setTitle($content['nom']['libelleFr']);
                $node->set('body', array(
                    'value' => $desc_body,
                    'format' => 'full_html',
                    'summary' => isset($content['presentation']['descriptifCourt']['libelleFr']) ? $content['presentation']['descriptifCourt']['libelleFr'] : text_summary($desc_body)
                ));
                $node->set('ao_type', $content['type']);

                // todo : setup yaml-based taxonomy (see https://www.metaltoad.com/blog/drupal-8-migrations-part-3-migrating-taxonomies-drupal-7)
                //      $type = $content['type'];
                //      $tid = _get_tid_from_type($type);
                //      $node->ao_type['und'][0] = array('tid' => $tid);

                // matched selections
                $selectionKey = "(" . $selection . ")";
                $selections = $node->ao_selections->value;
                $selections = isset($selections) ? explode(',', $selections) : array();

                if (!in_array($selectionKey, $selections)) {
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

                // moyens de communication
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

                // contacts
                if (isset($content['contacts'])) {
                    foreach ($content['contacts'] as $key => $value) {
                        if ($key < 3) {
                            $contact = $value['prenom'] . " " . $value['nom'] . " - " . $value['titre']['libelleFr'];
                            if (isset($value['moyensCommunication'])) {
                                foreach ($value['moyensCommunication'] as $kee => $val) {
                                    switch ($val['type']['id']) {
                                        case '201' :
                                            $contact .= "\nTéléphone : " . $val['coordonnees']['fr'];
                                            break;
                                        case '204' :
                                            $contact .= "\nEmail : " . $val['coordonnees']['fr'];
                                            break;
                                        default :
                                            break;
                                    }
                                }
                            }
                            $node->set('ao_contact' . ($key + 1), $contact);
                        }
                    }
                }

                if (isset($content['presentation']['descriptifCourt']['libelleFr'])) {
                    $node->set('ao_short_desc', $content['presentation']['descriptifCourt']['libelleFr']);
                }

                // pictures field
                $node->ao_pictures = [];
                if (isset($content['illustrations'])) {
                    foreach ($content['illustrations'] as $key => $value) {
                        if (isset($value['traductionFichiers'][0]['url'])) {
                            $node->ao_pictures[] = [
                                'title' => $value['nom']['libelleFr'],
                                'credits' => $value['copyright']['libelleFr'],
                                'url_large' => $value['traductionFichiers'][0]['url'],
                                'url_medium' => $value['traductionFichiers'][0]['urlDiaporama'],
                                'url_small' => $value['traductionFichiers'][0]['urlFiche']
                            ];
                        }
                    }
                }

                // attachments field
                $node->ao_attachments = [];
                if (isset($content['multimedias'])) {
                    foreach ($content['multimedias'] as $key => $value) {
                        if (isset($value['traductionFichiers'][0]['url'])) {
                            $node->ao_attachments[] = [
                                'title' => $value['nom']['libelleFr'],
                                'type' => $value['type'],
                                'url' => $value['traductionFichiers'][0]['url'],
                                'credits' => $value['copyright']['libelleFr'],
                                'description' => $value['legende']['libelleFr']
                            ];
                        }
                    }
                }

                if (isset($content['ouverture']['periodeEnClair']['libelleFr'])) {
                    $node->set('ao_openings', $content['ouverture']['periodeEnClair']['libelleFr']);
                }

                if (isset($content['descriptionTarif']['tarifsEnClair']['libelleFr'])) {
                    $node->set('ao_rates', $content['descriptionTarif']['tarifsEnClair']['libelleFr']);
                }

                if (isset($content['prestations']['animauxAcceptes'])) {
                    $node->set('ao_animals', $content['prestations']['animauxAcceptes']);
                }

                if (isset($content['prestations']['animauxAcceptesSupplement'])) {
                    $node->set('ao_animals_complement', $content['prestations']['animauxAcceptesSupplement']);
                }

                if (isset($content['prestations']['complementAccueil']['libelleFr'])) {
                    $node->set('ao_host_complement', $content['prestations']['complementAccueil']['libelleFr']);
                }

                // dates field
                $node->ao_dates = [];
                if (isset($content['ouverture']['periodesOuvertures'][0]['dateDebut'])) {
                    foreach ($content['ouverture']['periodesOuvertures'] as $key => $value) {
                        $node->ao_dates[] = $content['ouverture']['periodesOuvertures'][$key]['dateDebut'];
                    }
                }

                if (isset($content['informationsFeteEtManifestation']['typesManifestation'][0]['libelleFr'])) {
                    $node->set('ao_manifestation_type', $content['informationsFeteEtManifestation']['typesManifestation'][0]['libelleFr']);
                }

                if (isset($content['tourismesAdaptes']['libelleFr'])) {
                    $node->set('ao_adapted_tourism', $content['tourismesAdaptes']['libelleFr']);
                }

                if (isset($content['descriptifHandicapMoteur']['libelleFr'])) {
                    $node->set('ao_desc_motor_handicap', $content['descriptifHandicapMoteur']['libelleFr']);
                }

                if (isset($content['informations']['structureInformation']['nom']['libelleFr'])) {
                    $node->set('ao_structure_information', $content['informations']['structureInformation']['nom']['libelleFr']);
                }

                // descriptifs prives (ref values should be moved to configuration)
                if (isset($content['donneesPrivees'])) {
                    foreach ($content['donneesPrivees'] as $key => $value) {
                        if ($key < 3) {
                            $privateField = $value['nomTechnique'];
                            if ($privateField == '1486_References') {
                                $node->set('ao_privdesc1', $value['descriptif']['libelleFr']);
                            } elseif ($privateField == '1486_InformationsComplementaires') {
                                $node->set('ao_privdesc2', $value['descriptif']['libelleFr']);
                            }
                        }
                    }
                }

                // criteres internes (highly specific - ref values should be moved to config and code duplication removed)
                if (isset($content['criteresInternes'])) {
                    $refValues1 = [10205, 10261, 10264, 10263, 10265, 10258, 10269, 10267,
                        10268, 10256, 10260, 10270, 10262, 10259, 10266, 10257, 10233, 10271];
                    $refValues2 = [10376, 10378, 10375, 10377];
                    $refValues3 = [4359, 4360];
                    foreach ($content['criteresInternes'] as $key => $value) {
                        if (in_array($value['id'], $refValues1)) {
                            $internal = $node->ao_internal1->value;
                            $internal = isset($internal) ? explode(', ', $internal) : array();
                            if (!in_array($value['libelle'], $internal)) {
                                array_push($internal, $value['libelle']);
                                $node->set('ao_internal1', join(', ', $internal));
                            }
                        }
                        if (in_array($value['id'], $refValues2)) {
                            $internal = $node->ao_internal2->value;
                            $internal = isset($internal) ? explode(', ', $internal) : array();
                            if (!in_array($value['libelle'], $internal)) {
                                array_push($internal, $value['libelle']);
                                $node->set('ao_internal2', join(', ', $internal));
                            }
                        }
                        if (in_array($value['id'], $refValues3)) {
                            $internal = $node->ao_internal3->value;
                            $internal = isset($internal) ? explode(', ', $internal) : array();
                            if (!in_array($value['libelle'], $internal)) {
                                array_push($internal, $value['libelle']);
                                $node->set('ao_internal3', join(', ', $internal));
                            }
                        }
                    }
                }

                // type-specific criteria (highly specific also)
                if (isset($content['informationsEquipement']) && isset($content['informationsEquipement']['activites'])) {
                    $refValues = [4359, 4360, 4361, 4362, 4363, 4364, 4365];
                    foreach ($content['informationsEquipement']['activites'] as $key => $value) {
                        if (in_array($value['id'], $refValues)) {
                            $typeCriteria = $node->ao_type_criteria->value;
                            $typeCriteria = isset($typeCriteria) ? explode(', ', $typeCriteria) : array();
                            if (!in_array($value['libelleFr'], $typeCriteria)) {
                                array_push($typeCriteria, $value['libelleFr']);
                                $node->set('ao_type_criteria', join(', ', $typeCriteria));
                            }
                        }
                    }
                }

                // linked objects
                $node->ao_linked_objects = [];
                if (isset($content['liens']) && isset($content['liens']['liensObjetsTouristiquesTypes'])) {
                    foreach ($content['liens']['liensObjetsTouristiquesTypes'] as $key => $value) {
                        $linkId = $this->checkNodeExists($value['objetTouristique']['id']);
                        if (!is_null($linkId)) {
                            $linkAlias = \Drupal::service('path.alias_manager')->getAliasByPath('/node/' . $linkId);
                            $linkElt = $value['objetTouristique']['nom']['libelleFr'] . '|' . $linkAlias;

                            $node->ao_linked_objects[] = [
                                'title' => $value['objetTouristique']['nom']['libelleFr'],
                                'type' => $value['objetTouristique']['type'],
                                'url' => $linkAlias,
                            ];

                            // Note : to be removed
                            if (strpos(strtolower($value['objetTouristique']['nom']['libelleFr']), 'aappma') !== false) {
                                $node->set('ao_entity', $linkElt);
                            }
                        }
                    }
                }

                // Note : Unused for now - structures are not imported in project
                // managing entity (format is : label|url - tries to match an apidae object of type structure)
                if (isset($content['informations']) && isset($content['informations']['structureGestion'])) {
                    $managingEntity = $content['informations']['structureGestion'];
                    $entityId = $this->checkNodeExists($managingEntity['id']);
                    if (!is_null($entityId)) {
                        $linkAlias = \Drupal::service('path.alias_manager')->getAliasByPath('/node/' . $entityId);
                        $linkElt = $managingEntity['nom']['libelleFr'] . '|' . $linkAlias;
                        \Drupal::logger('Apidae query')->info('setting ao_entity to ' . $linkElt);
                        $node->set('ao_entity', $linkElt);
                    }
                }

                $node->save();
            } else {
                if (!is_null($nid)) {
                    \Drupal::logger('Apidae')->warning('Could not retrieve ' . $nid);
                }
            }
        }
    }
}