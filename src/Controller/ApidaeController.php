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
    const MAX_CYCLES = 50;

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
            $imported_ao_ids = [];
            foreach ($selections_ids as $selection) {
                $refreshMode = $this->config('system.apidae')->get('cron.type');
                $objectsCount = 0;
                $cycles = 0;
                $all_objects = [];
                $results = array();

                try {
                    while (($cycles == 0 || $objectsCount < $results['numFound']) && $cycles < self::MAX_CYCLES) {
                        $cycles += 1;
                        $results = $this->loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria,
                            $objectsCount, self::BATCH_SIZE);
                        if (isset($results['objetsTouristiques'])) {
                            $objectsCount += count($results['objetsTouristiques']);
                            $all_objects = array_merge($all_objects, array_values($results['objetsTouristiques']));
                        }
                    }
                    \Drupal::logger('Apidae query')->info("Selection " . $selection . " - Retrieved " . count($all_objects) . " objects");

                    foreach ($all_objects as $touristic_object) {
                        $imported_ao_ids[] = $this->createNode($touristic_object, $selection, $refreshMode);
                    }
                    \Drupal::logger('Apidae module')->info('%d objects updated/created successfully', array('%d' => $objectsCount));
                } catch (SitraException $e) {
                    \Drupal::logger('Apidae module')->error('An error occurred during the retrieval of Apidae data. Please make sure that all configuration values have been properly set.');
                    \Drupal::logger('Apidae module')->error($e->getMessage());
                }
            }
            $all_ao_ids = \Drupal::entityQuery('node')->condition('type', 'apidae_object', '=')->execute();
            $deleted_ao_ids = array_diff($all_ao_ids, $imported_ao_ids);
            if (count($deleted_ao_ids) > 0) {
                \Drupal::logger('Apidae module')->info('%d objects eligible for deletion : %o', array('%d' => count($deleted_ao_ids), '%o' => join(', ', $deleted_ao_ids)));
                $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($deleted_ao_ids);
                \Drupal::entityTypeManager()->getStorage('node')->delete($nodes);
                \Drupal::logger('Apidae module')->info('Deletion successful');
            } else {
                \Drupal::logger('Apidae module')->info('No objects to delete');
            }
        }

        return new Response('', 204);
    }

    private function loadApidaeResults($client, $apiKey, $apiProject, $selection, $typesCriteria, $offset, $count)
    {
        $results = $client->searchObject([
            'query' => [
                "apiKey" => $apiKey,
                "projetId" => $apiProject,
                "first" => $offset,
                "count" => $count,
                "selectionIds" => [$selection],
                "criteresQuery" => $typesCriteria,
                "responseFields" => ["id", "nom", "illustrations", "multimedias", "informations", "presentation",
                    "localisation", "@informationsObjetTouristique", "ouverture.periodeEnClair",
                    "ouverture.periodesOuvertures", "descriptionTarif.tarifsEnClair.libelleFr", "contacts", "liens",
                    "donneesPrivees", "criteresInternes", 'prestations', 'reservation', 'informationsEquipement']
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
                'projectId' => $id
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

    private function initApidaeObject()
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
        $existingId = $this->checkNodeExists($contentId);
        $nid = $existingId;

        if (is_null($existingId) || $refreshMode == 'new_content_and_updates') {

            if (is_null($existingId)) {
                $node = $this->initApidaeObject();
            } else {
                $node = \Drupal::entityTypeManager()->getStorage('node')->load($existingId);
            }
            if (!is_null($node)) {
                $desc_body = $content['presentation']['descriptifDetaille']['libelleFr'] ?? '';

                $node->set('ao_id', $contentId);
                $node->setTitle($content['nom']['libelleFr']);
                $node->set('body', array(
                    'value' => $desc_body,
                    'format' => 'full_html',
                    'summary' => $content['presentation']['descriptifCourt']['libelleFr'] ??  text_summary($desc_body)
                ));
                $node->set('ao_type', $content['type']);

                // matched selections
                $selectionKey = "(" . $selection . ")";
                $selections = $node->ao_selections->value;
                $selections = isset($selections) ? explode(',', $selections) : array();

                if (!in_array($selectionKey, $selections)) {
                    array_push($selections, $selectionKey);
                    $node->set('ao_selections', join(',', $selections));
                }

                // location data
                $node->set('ao_place', $content['localisation']['adresse']['nomDuLieu'] ?? null);
                $node->set('ao_address1', $content['localisation']['adresse']['adresse1'] ?? null);
                $node->set('ao_address2', $content['localisation']['adresse']['adresse2'] ?? null);
                $node->set('ao_address3', $content['localisation']['adresse']['adresse3'] ?? null);
                $node->set('ao_postal_code', $content['localisation']['adresse']['codePostal'] ?? null);
                $node->set('ao_town', $content['localisation']['adresse']['commune']['nom'] ?? null);
                $node->set('ao_latitude', $content['localisation']['geolocalisation']['geoJson']['coordinates']['1'] ?? null);
                $node->set('ao_longitude', $content['localisation']['geolocalisation']['geoJson']['coordinates']['0'] ?? null);

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
                            $contact = '';
                            if (isset($value['prenom']) && isset($value['nom'])) {
                                $contact .= $value['prenom'] . " " . $value['nom'];
                                if (isset($value['titre']) && isset($value['titre']['libelleFr'])) {
                                    $contact .= ' - ' . $value['titre']['libelleFr'];
                                }
                            }
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

                $node->set('ao_short_desc', $content['presentation']['descriptifCourt']['libelleFr'] ?? null);

                // pictures field
                $node->ao_pictures = [];
                if (isset($content['illustrations'])) {
                    foreach ($content['illustrations'] as $key => $value) {
                        if (isset($value['traductionFichiers'][0]['url'])) {
                            $node->ao_pictures[] = [
                                'title' => $value['nom']['libelleFr'] ?? null,
                                'credits' => $value['copyright']['libelleFr'] ?? null,
                                'url_large' => str_replace('http:', 'https:', $value['traductionFichiers'][0]['url']),
                                'url_medium' => str_replace('http:', 'https:', $value['traductionFichiers'][0]['urlDiaporama']),
                                'url_small' => str_replace('http:', 'https:', $value['traductionFichiers'][0]['urlFiche'])
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
                                'name' => $value['nom']['libelleFr'] ?? null,
                                'type' => $value['type'] ?? null,
                                'url' => $value['traductionFichiers'][0]['url'] ?? null,
                                'credits' => $value['copyright']['libelleFr'] ?? null,
                                'description' => $value['legende']['libelleFr'] ?? null
                            ];
                        }
                    }
                }

                // path / track info
                $node->ao_path = null;
                if (isset($content['informationsEquipement']) && isset($content['informationsEquipement']['itineraire'])) {
                    $node->ao_path = array(
                        'elevationGain' => $content['informationsEquipement']['itineraire']['denivellationPositive'] ?? null,
                        'type' => $content['informationsEquipement']['itineraire']['itineraireType'] ?? null,
                        'description' => $content['informationsEquipement']['itineraire']['precisionsBalisage']['libelleFr'] ?? null,
                        'duration' => $content['informationsEquipement']['itineraire']['dureeJournaliere'] ?? null,
                        'distance' => $content['informationsEquipement']['itineraire']['distance'] ?? null,
                        'waymarked' => $content['informationsEquipement']['itineraire']['itineraireBalise'] ?? null
                    );
                }

                $node->set('ao_openings', $content['ouverture']['periodeEnClair']['libelleFr'] ?? null);
                $node->set('ao_rates', $content['descriptionTarif']['tarifsEnClair']['libelleFr'] ?? null);
                $node->set('ao_animals', $content['prestations']['animauxAcceptes'] ?? null);
                $node->set('ao_animals_desc', $content['prestations']['descriptifAnimauxAcceptes']['libelleFr'] ?? '');
                $node->set('ao_host_complement', $content['prestations']['complementAccueil']['libelleFr'] ?? null);

                // dates field
                $node->ao_dates = [];
                if (isset($content['ouverture']['periodesOuvertures'][0]['dateDebut'])) {
                    foreach ($content['ouverture']['periodesOuvertures'] as $key => $value) {
                        if ($content['ouverture']['periodesOuvertures'][$key]['dateDebut'] >= date('Y-m-d')){
                            $node->ao_dates[] = $content['ouverture']['periodesOuvertures'][$key]['dateDebut'];
                        }
                    }
                }

                $node->set('ao_manifestation_type', $content['informationsFeteEtManifestation']['typesManifestation'][0]['libelleFr'] ?? null);

                $node->ao_adapted_tourism = '';
                if (isset($content['prestations']['tourismesAdaptes'])) {
                    $adapted_tourism = [];
                    foreach ($content['prestations']['tourismesAdaptes'] as $key => $value) {
                        array_push($adapted_tourism, $value['libelleFr']);
                    }
                    $node->set('ao_adapted_tourism', join('|', $adapted_tourism));
                }

                $node->set('ao_desc_motor_handicap', $content['prestations']['descriptifHandicapMoteur']['libelleFr'] ?? null);
                $node->set('ao_structure_information', $content['informations']['structureInformation']['nom']['libelleFr'] ?? null);
                $node->set('ao_booking_name', $content['reservation']['organismes'][0]['nom'] ?? null);

                $node->ao_booking_contacts = [];
                if (isset($content['reservation']['organismes'][0]['moyensCommunication'])) {
                    foreach ($content['reservation']['organismes'][0]['moyensCommunication'] as $key => $value) {
                        if (isset($content['reservation']['organismes'][0]['moyensCommunication'][$key]['coordonnees'])) {
                            $node->ao_booking_contacts[] = [
                                'coordonnees' => $content['reservation']['organismes'][0]['moyensCommunication'][$key]['coordonnees']['fr'] ?? null,
                                'observation' => $content['reservation']['organismes'][0]['moyensCommunication'][$key]['observation']['libelleFr'] ?? ''
                            ];
                        }
                    }
                }

                $node->set('ao_booking_desc', $content['reservation']['complement']['libelleFr'] ?? null);

                // descriptifs prives
                $node->ao_privdescs = [];
                if (isset($content['donneesPrivees'])) {
                    foreach ($content['donneesPrivees'] as $key => $value) {
                        $node->ao_privdescs[] = [
                            'key' => $value['nomTechnique'],
                            'value' => $value['descriptif']['libelleFr'] ?? null
                        ];
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
                            $linkAlias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $linkId);
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
                        $linkAlias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $entityId);
                        $linkElt = $managingEntity['nom']['libelleFr'] . '|' . $linkAlias;
                        \Drupal::logger('Apidae query')->info('setting ao_entity to ' . $linkElt);
                        $node->set('ao_entity', $linkElt);
                    }
                }

                $node->save();
                $nid = $node->id();
            } else {
                if (!is_null($existingId)) {
                    \Drupal::logger('Apidae')->warning('Could not retrieve ' . $existingId);
                }
            }
        }
        return $nid;
    }
}
