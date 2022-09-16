<?php

namespace App\Classes;

/**
 * Class for parsing the content from the page
 */
class PageUnitParseHotpadsCom
{
    // Page content
    protected $content;
    // Task information
    protected $task;

    /**
     * A builder with the parameters
     *
     * @param object $content
     * @param array $task
     */
    public function __construct($content, $task)
    {
        $this->content = $content;
        $this->task = $task;
    }

    /**
     * Parsing data from $content
     *
     * @return void
     */
    public function parse()
    {

        // type
        $type = isset($this->content->find('h2.PropertyTypeIcon-keyword')[0]) ?
            QueueHotpadsCom::clearText($this->content->find('h2.PropertyTypeIcon-keyword')[0]->text()) : '';

        // address
        $checkAddress = isset($this->content->find('div.HdpAddressV2 h2')[0]) ? true : false;

        $address = isset($this->content->find('div.HdpAddressV2')[0]) ?
            $this->content->find('div.HdpAddressV2') : '';

        if (!$checkAddress) {
            $addrLine2 = isset($address[0]->find('h2')[0]) ?
                QueueHotpadsCom::clearText($address[0]->find('h2')[0]->text()) : ''; // addr_line_2

            $addrLine1 = isset($address[0]->find('h1')[0]) ?
                QueueHotpadsCom::clearText($address[0]->find('h1')[0]->text()) : '';
            $addrLine1 = str_replace($addrLine2, '', $addrLine1); // addr_line_1

            $address = $addrLine1 . ', ' . $addrLine2; // address
            $city = explode(',', $addrLine2)[0]; // city
            $state = explode(' ', explode(',', $addrLine2)[1])[1]; // state_cd
            $zip = explode(' ', explode(',', $addrLine2)[1])[2]; // zip5_cd
            $building_name = null; // building_name
        } elseif ($checkAddress) {
            $building_name_temp = QueueHotpadsCom::clearText($address[0]->find('h1')[0]->text());

            $addrLine2 = QueueHotpadsCom::clearText($address[0]->xpath('.//address/text()[preceding-sibling::br]')[0]);
            $city = explode(',', $addrLine2)[0];
            $state = explode(' ', explode(',', $addrLine2)[1])[1];
            $zip = explode(' ', explode(',', $addrLine2)[1])[2];

            $address = QueueHotpadsCom::clearText($address[0]->find('h2')[0]->text());

            $addrLine1 = str_replace($addrLine2, '', $address);

            $data = [
                "sq",
                "$",
                "bed",
                "bath",
                "sqft",
                "available"
            ];

            foreach ($data as $temp) {
                if (strpos(mb_strtolower($building_name_temp), $temp)) {
                    $building_name = null;
                    break;
                }
                $building_name = $building_name_temp; // building_name
            }
        }

        // pet policy
        $pets = $this->content->xpath(
            "//article[contains(@class, 'HdpContentWrapper') and contains(.//h2, 'Pet policy')]"
        )[0]->find('span.styles__Text-me4q2-0');

        $petPolicy = []; // pet_policy
        foreach ($pets as $pet) {
            $petPolicy[] = QueueHotpadsCom::clearText($pet->text());
        }

        // contact

        $phone = isset($this->content->find('div.ContactPhone')[0]) ?
            QueueHotpadsCom::clearText($this->content->find('div.ContactPhone')[0]->text()) : ''; // contact_phone
        $contactPerson = isset($this->content->find('div.ContactListedBy-name')[0]) ?
            QueueHotpadsCom::clearText($this->content->find('div.ContactListedBy-name')[0]->text()) : '';

        if ($contactPerson === 'Message Contact Manager') {
            $contactPerson = '';
        }

        $contactPerson = str_replace('Message ', '', $contactPerson); // contact_person

        // highlights

        // $features = $this->content->find('ul.HdpHighlights-list')[0]->find('li.HdpHighlights-item');
        $features = $this->content->xpath(
            "//ul[contains(@class, 'HighlightsList')]"
        )[0]->find('li');

        $onPremiseFeatures = []; // on_premise_features
        foreach ($features as $feature) {
            $onPremiseFeatures[] = QueueHotpadsCom::clearText($feature->text());
        }

        // amenities
        $amenitiesSection = isset($this->content->find('div.HdpAmenitySection')[0]) ?
            $this->content->find('div.HdpAmenitySection')[0]->find('li.ListItem') : '';

        if ($amenitiesSection) {
            $amenities = []; // property_info
            foreach ($amenitiesSection as $amenity) {
                $amenities[] = QueueHotpadsCom::clearText($amenity->text());
            }
        }

        // descritpion

        $desc = isset($this->content->find('div#HdpDescriptionContent')[0]) ?
            QueueHotpadsCom::clearText($this->content->find('div#HdpDescriptionContent')[0]->text()) : ''; // building_desc

        // nearby school

        $schoolsBlock = isset($this->content->find('ul.Schools')[0]) ?
            $this->content->find('ul.Schools')[0]->find('li.SchoolItem') : '';

        $schools = []; // nearby_school
        foreach ($schoolsBlock as $school) {
            $schoolTemp['title'] = QueueHotpadsCom::clearText($school->find('h3.SchoolItem-name')[0]->text());
            $schoolTemp['type'] = QueueHotpadsCom::clearText($school->find('div.SchoolItem-type')[0]->text());
            $schoolTemp['grade'] = QueueHotpadsCom::clearText($school->find('div.SchoolItem-grades')[0]->text());
            $schoolTemp['distance'] = QueueHotpadsCom::clearText($school->find('div.SchoolItem-distance')[0]->text());
            $schoolTemp['rating'] = QueueHotpadsCom::clearText($school->find('div.SchoolRatingIcon-circle')[0]->text());

            array_push($schools, $schoolTemp);
        }

        // availability

        $header = isset($this->content->find('div.SingleModelHdpHeader')[0]) ?
            $this->content->find('div.SingleModelHdpHeader') : '';

        $header = (null !== $this->content->xpath("//div[contains(@class, 'HdpSummaryDetails__DetailRow')]")) ? $this->content->xpath("//div[contains(@class, 'HdpSummaryDetails__DetailRow')]") : '';

        $hasMultiModels = isset($this->content->find('div.MultiModelsGroup-container')[0]) ? $this->content->find('div.MultiModelsGroup-container') : '';
        $models = [];


        if (!$hasMultiModels) { // If there is only one floor plan
            // print_r($header[0]->child(0)->child(0)->text());

            $price = (null !== $header[0]->child(0)->child(0)) ?
                QueueHotpadsCom::clearText($header[0]->child(0)->child(0)->text()) : ''; // listing_price

            $status = ''; // status

            // $badsBathSqft = isset($header[0]->find('div.BedsBathsSqft')[0]) ? $header[0]->find('div.BedsBathsSqft')[0]->find('div.BedsBathsSqft-item') : '';

            $beds = (null !== $header[0]->child(1)) ? QueueHotpadsCom::clearText($header[0]->child(1)->child(0)->text()) : ''; // bedroom_cnt
            $bath = (null !== $header[0]->child(2)) ? QueueHotpadsCom::clearText($header[0]->child(2)->child(0)->text()) : ''; // bathroom_cnt
            $sqft = (null !== $header[0]->child(3)) ? QueueHotpadsCom::clearText($header[0]->child(3)->child(0)->text()) : ''; // home_size_sq_ft

            echo 'Price - ' . $price . ' | ' . 'Beds - ' . $beds . ' | ' . 'Baths - ' . $bath . ' | ' . 'SQFT - ' . $sqft . PHP_EOL;
        } else { // If there are multiple floor plans
            $multiModels = $this->content->find('div.MultiModelsGroup-container')[0]->find('div.MultiModelsGroup-floorplan-item');

            foreach ($multiModels as $model) {
                $beds = isset($model->find('span.ModelFloorplanItem-detail')[0]) ?
                    QueueHotpadsCom::clearText($model->find('span.ModelFloorplanItem-detail')[0]->text()) : ''; // bedroom_cnt
                $bath = isset($model->find('span.ModelFloorplanItem-bthsqft')[0]) ?
                    QueueHotpadsCom::clearText($model->find('span.ModelFloorplanItem-bthsqft')[0]->text()) : ''; // bathroom_cnt
                $sqft = isset($model->find('span.ModelFloorplanItem-bthsqft')[1]) ?
                    QueueHotpadsCom::clearText($model->find('span.ModelFloorplanItem-bthsqft')[1]->text()) : ''; // home_size_sq_ft
                $image = isset($model->find('div.FloorplanImage-container')[0]) ?
                    $model->find('div.FloorplanImage-container')[0]->find('img.FloorplanImage')[0]
                    ->getAttribute('src') : ''; // image_urls

                $modelFloorPlans = $model->find('div.ModelFloorplanItem-unit');

                if ($modelFloorPlans) { // If price block is filled
                    foreach ($modelFloorPlans as $plan) {
                        $price = QueueHotpadsCom::clearText($plan->find('div.ModelFloorplanItem-unit-price')[0]->text()); // listing_price
                        $status = QueueHotpadsCom::clearText($plan->find('div.ModelFloorplanItem-unit-availability')[0]->text()); // status

                        array_push($models, [
                            'bedroom_cnt' => $beds,
                            'bathroom_cnt' => $bath,
                            'home_size_sq_ft' => $sqft,
                            'listing_price' => $price,
                            'status' => $status,
                            'image_urls' => json_encode($image)
                        ]);
                    }
                } else {
                    $price = QueueHotpadsCom::clearText($model->find('div.ModelFloorplanItem-empty-unit-price')[0]->text());

                    array_push($models, [
                        'bedroom_cnt' => $beds,
                        'bathroom_cnt' => $bath,
                        'home_size_sq_ft' => $sqft,
                        'listing_price' => $price,
                        'image_urls' => json_encode($image)
                    ]);
                }
            }
        }

        // images

        $image = $this->content->find('div.PhotoCarousel')[0]->find('li img')[0]->getAttribute('src'); // image_urls

        // database
        $db = new MySQL('parsing','local');

        // Filling the fields in the properties table
        $data = [
            'link' => $this->task['link'],
            'image_urls' => json_encode($image),
            'addr_line_2' => $addrLine2,
            'addr_line_1' => $addrLine1,
            'address' => $address,
            'city' => $city,
            'state_cd' => $state,
            'zip5_cd' => $zip,
            'pet_policy' => json_encode($petPolicy),
            'contact_phone' => $phone,
            'contact_person' => $contactPerson,
            'on_premise_features' => json_encode($onPremiseFeatures),
            'property_info' => json_encode($amenities),
            'building_name' => $building_name,
            'building_desc' => $desc,
            'nearby_school' => json_encode($schools),
            'type' => $type
        ];

        // Updating or creating the record in rental
        $idProperty = $db->updateOrCreate('properties', $data);

        // Filling the fields in the availability table
        $data = [
            'listing_price' => $price,
            'bedroom_cnt' => $beds,
            'bathroom_cnt' => $bath,
            'home_size_sq_ft' => $sqft,
            'status' => $status,
            'property_id' => $idProperty[0]
        ];

        // Checking for update
        if ($idProperty[1] === 'update') {
            $db->deleteAvailability($idProperty[0]);
        }

        // Adding a record into availability
        if ($models) {
            foreach ($models as $model) {
                $model['property_id'] = $idProperty[0];
                $db->insert('availability', $model);
            }
        } else {
            $db->insert('availability', $data);
        }


        echo 'SUCCESS: ' . $idProperty[1] . ' ID prop: ' . $idProperty[0] . ' | ';

        return true;
    }
}
