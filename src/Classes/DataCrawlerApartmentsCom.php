<?php

namespace App\Classes;

use Exception;
use JsonException;
use RuntimeException;

class DataCrawlerApartmentsCom
{
    public function parse($document, $uri, $method, $by = null)
    {
        if ($document != '') {
            file_put_contents(LOG_DIR . '/apartments-com-data-crawler.log', '[' . date('Y-m-d H:i:s') . '] ' . $uri . ' - ' . $method . PHP_EOL, FILE_APPEND);
            if ($method === 'get_links') {
                if ($document->count('div.no-results') === 0 && $document->count('.noPlacards') === 0) {
                    $data = $document->find('article');

                    foreach ($data as $article) {
                        $link = $article->attr('data-url');
                        if ($link) {
                            $task = '{"link":"' . $link . '", "method":"parse", "by":"' . $by . '"}';
                            Redis::init()->rpush('tasks', $task);
                        }
                    }

                    // navigate by pagination
                    $pageNumber = explode('/', trim($uri, '/'));
                    $pageNumber = is_numeric(end($pageNumber)) ? end($pageNumber) : 1;
                    if ($pageNumber === 1) {
                        $pageCount = isset($document->find('span.pageRange')[0]) ? end(explode(' ', trim($document->find('span.pageRange')[0]->text()))) : 1;
                        if ($pageCount > 1) {
                            for ($i = 2; $i <= $pageCount; $i++) {
                                $link = $uri . $i . '/';
                                $task = '{"link":"' . $link . '", "method":"get_links", "by":"' . $by . '"}';
                                Redis::init()->rpush('tasks', $task);
                            }
                        }
                    }
                }
            } elseif ($method === 'parse') {
                $db = new MySQL('parsing','local');
                $query = $db->pdo->prepare("SELECT count(*) FROM `properties` WHERE `link` = ? LIMIT 1");
                $query->execute([$uri]);
                $isDuplicate = $query->fetchColumn();
                if (!$isDuplicate) {
                    $key = end(explode('/', rtrim($uri, '/')));

                    // building name
                    $buildingName = isset($document->find('h1.propertyName')[0]) ? trim($document->find('h1.propertyName')[0]->text()) : '';
                    // contact phone
                    $contactPhone = isset($document->find('div.phoneNumber')[0]) ? trim($document->find('div.phoneNumber')[0]->text()) : '';
                    if ($contactPhone == '') {
                        $contactPhone = isset($document->find('span.contactPhone')[0]) ? trim($document->find('span.contactPhone')[0]->text()) : '';
                    }
                    // latitude & longtitude
                    $latitude = isset($document->xpath('//meta[@property="place:location:latitude"]/@content')[0]) ? trim($document->xpath('//meta[@property="place:location:latitude"]/@content')[0]) : '';

                    $longitude = isset($document->xpath('//meta[@property="place:location:longitude"]/@content')[0]) ? trim($document->xpath('//meta[@property="place:location:longitude"]/@content')[0]) : '';
                    // last updated
                    $lastUpdated = isset($document->find('div.freshnessContainer')[0]) ? trim($document->find('div.freshnessContainer')[0]->text()) : '';
                    // property info
                    $property = isset($document->find('div.uniqueFeatures li')[0]) ?
                        $document->find('div.uniqueFeatures li') : '';
                    if ($property) {
                        $propertyInfo = [];
                        foreach ($property as $p) {
                            $propertyInfo[] = $this->clearText($p->text());
                        }
                        $propertyInfo = json_encode($propertyInfo, JSON_PRETTY_PRINT);
                    }
                    // student features
                    $studentFeature = isset($document->xpath("//h3[contains(text(), 'Student Features')]/parent::div/div/ul")[0]) ?
                        $document->xpath("//h3[contains(text(), 'Student Features')]/parent::div/div/ul/li") : '';
                    if ($studentFeature) {
                        $studentFeatures = [];
                        foreach ($studentFeature as $sf) {
                            $studentFeatures[] = $this->clearText($sf->text());
                        }
                        $studentFeatures = json_encode($studentFeatures, JSON_PRETTY_PRINT);
                    }
                    // neighborhood comments
                    $neighborhoodComment = isset($document->xpath("//h2[contains(text(), 'Neighborhood')]/parent::section")[0]) ?
                        $document->xpath("//h2[contains(text(), 'Neighborhood')]/parent::section")[0] : '';
                    $neighborhoodComments = [];
                    if ($neighborhoodComment) {
                        $neighborhoodComment = $neighborhoodComment->find('p');
                        foreach ($neighborhoodComment as $nc) {
                            $neighborhoodComments[] = $this->clearText($nc->text());
                        }
                    }
                    $neighborhoodComments = implode("\n", $neighborhoodComments);
                    // contact person
                    $contactPerson = isset($document->find('div.agentFullName')[0]) ? trim($document->find('div.agentFullName')[0]->text()) : '';
                    // building description
                    $buildingDesc = isset($document->find('section.descriptionSection')[0]) ?
                        $this->clearText($document->find('section.descriptionSection')[0]->text()) : '';
                    // walk score
                    $walkScore = isset($document->find('div.walkScore div.score')[0]) ?
                        $this->clearText($document->find('div.walkScore div.score')[0]->text()) : '';
                    // transit score
                    $transitScore = isset($document->find('div.transitScore div.score')[0]) ?
                        $this->clearText($document->find('div.transitScore div.score')[0]->text()) : '';
                    // address
                    $address = isset($document->find('div.propertyAddressRow h2')[0]) ?
                        preg_replace('/\s\s+/', ' ', $this->clearText($document->find('div.propertyAddressRow h2')[0]->text())) : '';
                    $addr1 = isset($document->find('h1.propertyName')[0]) ?
                        $this->clearText($document->find('h1.propertyName')[0]->text()) : '';
                    $addr2 = isset($document->find('div.propertyAddressContainer span')[0]) ?
                        $this->clearText($document->find('div.propertyAddressContainer span')[0]->text()) : '';
                    // parse address
                    $state = isset($document->find('div.propertyAddressContainer span.stateZipContainer')[0]) ?
                        $this->clearText($document->find('div.propertyAddressContainer span.stateZipContainer')[0]->find('span')[1]->text()) : [];
                    $zip5Code = isset($document->find('div.propertyAddressContainer span.stateZipContainer')[0]) ?
                        $this->clearText($document->find('div.propertyAddressContainer span.stateZipContainer')[0]->find('span')[2]->text()) : [];
                    $city = isset($document->find('div.propertyAddressContainer')[0]) ?
                        $this->clearText($document->find('div.propertyAddressContainer')[0]->find('span')[1]->text()) : [];
                    //on_promise_services and on_promise_features
                    $amenitiesSection = isset($document->find('section.amenitiesSection')[0]) ?
                        $document->find('section.amenitiesSection')[0]->find('div.spec') : [];
                    if (count($amenitiesSection) == 2) { //if both blocks exist
                        $amenitiesList = $this->amenitiesBlock($amenitiesSection[0]->find('div.specGroup'));
                        $appartmentFeatures = $this->amenitiesBlock($amenitiesSection[1]->find('div.specGroup'));
                    } elseif (count($amenitiesSection) == 1) { // if there is any one block
                        $section_amenitiesSection = $document->find('section.amenitiesSection')[0];
                        // var_dump($section_amenitiesSection);
                        if ($section_amenitiesSection && $section_amenitiesSection->find('h2.sectionTitle')[0]->text() == 'Apartment Features') { //for block Apartment Features
                            $appartmentFeatures = $this->amenitiesBlock($amenitiesSection[0]->find('div.specGroup'));
                        } else { //for block Community Amenities
                            $amenitiesList = $this->amenitiesBlock($amenitiesSection[0]->find('div.specGroup'));
                        }
                    }
                    //images url
                    $imagesLiArr = $document->find('section.carouselSection ul')[0]->find('li');
                    if ($imagesLiArr) {
                        $images = [];
                        foreach ($imagesLiArr as $li) {
                            $imgs = $li->find('img');
                            foreach ($imgs as $img) {
                                $images[] = $img->getAttribute('src');
                            }
                        }
                        $images = json_encode($images, JSON_PRETTY_PRINT);
                    }
                    // availability
                    $availability = [];

                    $availabilityInfo = $document->find('.availabilityInfo');
                    // Single House availability
                    if(isset($availabilityInfo) && !empty($availabilityInfo) && count($availabilityInfo) == 1) {
                        // bedroom count
                        $bedroomCnt = isset($document->find('.priceBedRangeInfoInnerContainer')[1]->find('.rentInfoDetail')[0]) ?
                            $this->clearText($document->find('.priceBedRangeInfoInnerContainer')[1]->find('.rentInfoDetail')[0]->text()) : '';
                        // bathroom count
                        $bathroomCnt = isset($document->find('.priceBedRangeInfoInnerContainer')[2]->find('.rentInfoDetail')[0]) ?
                            $this->clearText($document->find('.priceBedRangeInfoInnerContainer')[2]->find('.rentInfoDetail')[0]->text()) : '';
                        // listing price
                        $listingPrice = isset($document->find('.priceBedRangeInfoInnerContainer')[0]->find('.rentInfoDetail')[0]) ?
                            $this->clearText($document->find('.priceBedRangeInfoInnerContainer')[0]->find('.rentInfoDetail')[0]->text()) : '';
                        // home size sq ft
                        $sqft = isset($document->find('.priceBedRangeInfoInnerContainer')[3]->find('.rentInfoDetail')[0]) ?
                            $this->clearText($document->find('.priceBedRangeInfoInnerContainer')[3]->find('.rentInfoDetail')[0]->text()) : '';
                        // lease length
                        // $leaseLengthAvailability = isset($tr->find('td.leaseLength')[0]) ? $this->clearText($tr->find('td.leaseLength')[0]->text()) : '';
                        // status
                        $status = $this->clearText($availabilityInfo[0]->text());

                        // $rentalId = $tr->attr('data-rentalkey');
                        // $rentalType = $tr->attr('data-rentaltype');

                        array_push($availability, [
                            'bedroom_cnt' => $bedroomCnt,
                            'bathroom_cnt' => $bathroomCnt,
                            'listing_price' => $listingPrice,
                            'home_size_sq_ft' => $sqft,
                            'lease_length' => '',
                            'status' => $status,
                            'image_urls' => ''
                        ]);
                    // Single House availability END
                    } elseif ($document->find('#bedsFilterContainer')) {
                        $section_all = $document->find('div.tab-section')[0];
                        if(isset($section_all) && $section_all != '') {
                            $pricingGridItems = $section_all->find('.pricingGridItem');
                            for ($i = 0; $i < count($pricingGridItems); $i++) {
                                $pricingGridItem = $pricingGridItems[$i];
                                $detailsTextWrapper = $pricingGridItem->find('.detailsTextWrapper')[0];
                                // echo ' detailsTextWrapper' . $pricingGridItem->find('.detailsTextWrapper')[0];
                                $bedroomCnt = isset($detailsTextWrapper->find('span')[1]) ?
                                    $this->clearText($detailsTextWrapper->find('span')[1]->text()) : '';
                                // echo ' bedroomCnt - ' . $detailsTextWrapper->find('span')[1]->text();
                                $bathroomCnt = isset($detailsTextWrapper->find('span')[2]) ?
                                    $this->clearText($detailsTextWrapper->find('span')[2]->text()) : '';
                                $sqft = isset($detailsTextWrapper->find('span')[3]) ?
                                    $this->clearText($detailsTextWrapper->find('span')[3]->text()) : '';      
                                $listingPrice = isset($pricingGridItem->find('.rentLabel')[0]) ?
                                    $this->clearText($pricingGridItem->find('.rentLabel')[0]->text()) : ''; 
                                $status = isset($pricingGridItem->find('.availabilityInfo')[0]) ?
                                    $this->clearText($pricingGridItem->find('.availabilityInfo')[0]->text()) : 'Not Available';                             
                                $unitGridContainer = $pricingGridItem->find('.unitGridContainer')[0];
                                if(isset($unitGridContainer) && $unitGridContainer !== null) {
                                    $unitContainers = $unitGridContainer->find('.unitContainer');
                                    foreach($unitContainers as $unitContainer) {
                                        $pricingColumn = $unitContainer->find('.pricingColumn');
                                        $listingPriceSpan = $pricingColumn[0]->find('span');
                                        $listingPrice = (isset($listingPriceSpan) && $listingPriceSpan != null) ? $this->clearText($listingPriceSpan[1]->text()) : '';
                                        $sqftColumn = $unitContainer->find('.sqftColumn');
                                        $sqftSpan = $sqftColumn[0]->find('span');
                                        $sqft = (isset($sqftSpan) && $sqftSpan != null) ? $this->clearText($sqftSpan[1]->text()) : '';   
                                        $statusSpan = $unitContainer->find('.dateAvailable');
                                        if(isset($statusSpan) && $statusSpan != null) {
                                            $status = trim(str_replace('availibility','',$this->clearText($statusSpan[0]->text())));
                                        }
                                        
                                        $rentalId = '';
                                        $rentalType = '';
                                        $images = '';                                        

                                        $rentalId = $unitContainer->attr('data-rentalkey');
                                        $rentalType = $unitContainer->attr('data-rentaltype');
                
                                        $imgcrw = new ImageUnitCrawlerApartmentsCom;
                                        $images = $imgcrw->send($key, $rentalId, $rentalType);          
                                        
                                        array_push($availability, [
                                            'bedroom_cnt' => $bedroomCnt,
                                            'bathroom_cnt' => $bathroomCnt,
                                            'listing_price' => $listingPrice,
                                            'home_size_sq_ft' => $sqft,
                                            'lease_length' => '',
                                            'status' => $status,
                                            'image_urls' => $images
                                        ]);                                    
                                    }
                                } else {
                                    $floorplanButton = $pricingGridItem->find('.floorplanButton')[0];
                                    $rentalId = '';
                                    $rentalType = '';
                                    $images = '';

                                    if(isset($floorplanButton) && $floorplanButton != null) {
                                        $rentalId = $floorplanButton->attr('data-rentalkey');
                                        $rentalType = $floorplanButton->attr('data-rentaltype');

                                        $imgcrw = new ImageUnitCrawlerApartmentsCom;
                                        $images = $imgcrw->send($key, $rentalId, $rentalType);   
                                    }       

                                    array_push($availability, [
                                        'bedroom_cnt' => $bedroomCnt,
                                        'bathroom_cnt' => $bathroomCnt,
                                        'listing_price' => $listingPrice,
                                        'home_size_sq_ft' => $sqft,
                                        'lease_length' => '',
                                        'status' => $status,
                                        'image_urls' => $images
                                    ]);                                     
                                }
                            }
                        }
                    } else {
                        $availabilityTable = $document->find('tr.rentalGridRow');

                        for ($i = 0; $i < count($availabilityTable); $i++) {
                            $tr = $availabilityTable[$i];
                            $bedroomCnt = isset($tr->find('td.beds span.longText')[0]) ?
                                $this->clearText($tr->find('td.beds span.longText')[0]->text()) : '';
                            // bedroom count
                            $bathroomCnt = isset($tr->find('td.baths span.longText')[0]) ?
                                $this->clearText($tr->find('td.baths span.longText')[0]->text()) : '';
                            // listing price
                            $listingPrice = isset($tr->find('td.rent')[0]) ?
                                $this->clearText($tr->find('td.rent')[0]->text()) : '';
                            // home size sq ft
                            $sqft = isset($tr->find('td.sqft')[0]) ?
                                $this->clearText($tr->find('td.sqft')[0]->text()) : '';
                            // lease length
                            $leaseLengthAvailability = isset($tr->find('td.leaseLength')[0]) ?
                                $this->clearText($tr->find('td.leaseLength')[0]->text()) : '';
                            // status
                            $status = isset($tr->find('td.available')[0]) ?
                                $this->clearText($tr->find('td.available')[0]->text()) : '';
    
                            $rentalId = $tr->attr('data-rentalkey');
                            $rentalType = $tr->attr('data-rentaltype');
    
                            $imgcrw = new ImageUnitCrawlerApartmentsCom;
                            $images = $imgcrw->send($key, $rentalId, $rentalType);
    
                            array_push($availability, [
                                'bedroom_cnt' => $bedroomCnt,
                                'bathroom_cnt' => $bathroomCnt,
                                'listing_price' => $listingPrice,
                                'home_size_sq_ft' => $sqft,
                                'lease_length' => $leaseLengthAvailability,
                                'status' => $status,
                                'image_urls' => $images
                            ]);
                        }
                    }
                    // nearby colleges
                    $elem = $document->xpath("//th[contains(text(), 'Colleges')]/ancestor::table/tbody/tr") ?? [];
                    $timeType = $document->xpath("//th[contains(text(), 'Colleges')]/ancestor::table/thead")[0] ?? '';
                    $timeType = $timeType != '' ? $timeType->find('th')[1]->text() : '';
                    $nearbyColleges = $this->getNearbyData($elem, $timeType);
                    // nearby transit
                    $elem = $document->xpath("//th[contains(text(), 'Transit / Subway')]/ancestor::table/tbody/tr") ?? [];
                    $timeType = $document->xpath("//th[contains(text(), 'Transit / Subway')]/ancestor::table/thead")[0] ?? '';
                    $timeType = $timeType != '' ? $timeType->find('th')[1]->text() : '';
                    $nearbyTransit = $this->getNearbyData($elem, $timeType);
                    // nearby rail
                    $elem = $document->xpath("//th[contains(text(), 'Commuter Rail')]/ancestor::table/tbody/tr") ?? [];
                    $timeType = $document->xpath("//th[contains(text(), 'Commuter Rail')]/ancestor::table/thead")[0] ?? '';
                    $timeType = $timeType != '' ? $timeType->find('th')[1]->text() : '';
                    $nearbyRail = $this->getNearbyData($elem, $timeType);
                    // nearby shopping
                    $elem = $document->xpath("//th[contains(text(), 'Shopping Centers')]/ancestor::table/tbody/tr") ?? [];
                    $timeType = $document->xpath("//th[contains(text(), 'Shopping Centers')]/ancestor::table/thead")[0] ?? '';
                    $timeType = $timeType != '' ? $timeType->find('th')[1]->text() : '';
                    $nearbyShopping = $this->getNearbyData($elem, $timeType);
                    // nearby parks
                    $elem = $document->xpath("//th[contains(text(), 'Parks and Recreation')]/ancestor::table/tbody/tr") ?? [];
                    $timeType = $document->xpath("//th[contains(text(), 'Parks and Recreation')]/ancestor::table/thead")[0] ?? '';
                    $timeType = $timeType != '' ? $timeType->find('th')[1]->text() : '';
                    $nearbyParks = $this->getNearbyData($elem, $timeType);
                    // nearby airports
                    $elem = $document->xpath("//th[contains(text(), 'Airports')]/ancestor::table/tbody/tr") ?? [];
                    $timeType = $document->xpath("//th[contains(text(), 'Airports')]/ancestor::table/thead")[0] ?? '';
                    $timeType = $timeType != '' ? $timeType->find('th')[1]->text() : '';
                    $nearbyAirports = $this->getNearbyData($elem, $timeType);
                    // utilities included
                    $utilsIncluded = isset($document->find('div.freeUtilities')[0]) ?
                        $this->clearText($document->find('div.freeUtilities')[0]->find('.descriptionWrapper span')[0]->text()) : '';
                    // expences
                    $expencesArr = isset($document->find('div.oneTimeFees')[0]) ? $document->find('div.oneTimeFees')[0]->find('.descriptionWrapper') : [];
                    $expences = [];
                    foreach ($expencesArr as $ex) {
                        $ex = $ex->find('span');
                        $expence = $ex[0]->text() ?? '';
                        $cost = $ex[1]->text() ?? '';
                        array_push($expences, [
                            'expence' => $expence,
                            'cost' => $cost,
                        ]);
                    }
                    $expences = !empty($expences) ? json_encode($expences, JSON_PRETTY_PRINT) : '';
                    // office hours
                    $officeHoursArr = isset($document->find('div.officeHours')[0]) ?
                        $document->find('div.officeHours')[0]->find('li.daysHoursContainer') : '';
                    $officeHours = [];
                    foreach ($officeHoursArr as $oh) {
                        $days = $this->clearText($oh->first('.days')->text());
                        $hours = $this->clearText($oh->first('.hours')->text());
                        $officeHours[] = [
                            'days' => $days,
                            'hours' => $hours
                        ];
                    }
                    $officeHours = !empty($officeHours) ? json_encode($officeHours, JSON_PRETTY_PRINT) : '';
                    // type
                    $type = isset($document->find('span.crumb')[0]) ? $document->find('span.crumb')[0]->first('a')->attr('data-type') : '';
                    $type = ucfirst(substr_replace($type, "", -1));
                    $type = $type == 'Condo' ? 'Apartment' : $type;

                    $date = date('Y-m-d H:i:s');

                    $zipDB = file_get_contents(__DIR__ . '/../zipcodes.json');
                    $zipDB = json_decode($zipDB, true);
                    if ($by == 'zip') {
                        if (in_array($zip5Code, $zipDB)) {
                            // paste properties
                            $query = $db->pdo->prepare("INSERT INTO `properties` (
                                `address`,
                                `type`,
                                `addr_line_1`,
                                `addr_line_2`,
                                `building_name`,
                                `contact_phone`,
                                `latitude`,
                                `longitude`,
                                `listing_last_updated`,
                                `property_info`,
                                `on_premise_services`,
                                `student_features`,
                                `on_premise_features`,
                                `contact_person`,
                                `building_desc`,
                                `walk_score`,
                                `transit_score`,
                                `link`,
                                `city`,
                                `zip5_cd`,
                                `state_cd`,
                                `image_urls`,
                                `nearby_colleges`,
                                `nearby_transit`,
                                `nearby_rail`,
                                `nearby_shopping`,
                                `nearby_parks`,
                                `nearby_airports`,
                                `neighborhood_comments`,
                                `utilities_included`,
                                `expences`,
                                `builiding_office_hours`,
                                `date_added`
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $query->execute([
                                $address,
                                $type,
                                $addr1,
                                $addr2,
                                $buildingName,
                                $contactPhone,
                                $latitude,
                                $longitude,
                                $lastUpdated,
                                $propertyInfo,
                                $amenitiesList,
                                $studentFeatures,
                                $appartmentFeatures,
                                $contactPerson,
                                $buildingDesc,
                                $walkScore,
                                $transitScore,
                                $uri,
                                $city,
                                $zip5Code,
                                $state,
                                $images,
                                $nearbyColleges,
                                $nearbyTransit,
                                $nearbyRail,
                                $nearbyShopping,
                                $nearbyParks,
                                $nearbyAirports,
                                $neighborhoodComments,
                                $utilsIncluded,
                                $expences,
                                $officeHours,
                                $date,
                            ]);

                            // paste avialability
                            $query = $db->pdo->prepare("SELECT `id` FROM `properties` WHERE `link` = ? LIMIT 1");
                            $query->execute([$uri]);
                            $prop = $query->fetch();
                            if ($prop->id) {
                                $propId = $prop->id;
                                foreach ($availability as $item) {
                                    $query = $db->pdo->prepare("INSERT INTO `availability` (
                                        `property_id`,
                                        `bedroom_cnt`,
                                        `bathroom_cnt`,
                                        `listing_price`,
                                        `home_size_sq_ft`,
                                        `lease_length`,
                                        `status`,
                                        `image_urls`
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                    $query->execute([
                                        $propId,
                                        $item['bedroom_cnt'],
                                        $item['bathroom_cnt'],
                                        $item['listing_price'],
                                        $item['home_size_sq_ft'],
                                        $item['lease_length'],
                                        $item['status'],
                                        $item['image_urls']
                                    ]);
                                }
                            }
                        }
                    } else {
                        // paste properties
                        $query = $db->pdo->prepare("INSERT INTO `properties` (
                            `address`,
                            `type`,
                            `addr_line_1`,
                            `addr_line_2`,
                            `building_name`,
                            `contact_phone`,
                            `latitude`,
                            `longitude`,
                            `listing_last_updated`,
                            `property_info`,
                            `on_premise_services`,
                            `student_features`,
                            `on_premise_features`,
                            `contact_person`,
                            `building_desc`,
                            `walk_score`,
                            `transit_score`,
                            `link`,
                            `city`,
                            `zip5_cd`,
                            `state_cd`,
                            `image_urls`,
                            `nearby_colleges`,
                            `nearby_transit`,
                            `nearby_rail`,
                            `nearby_shopping`,
                            `nearby_parks`,
                            `nearby_airports`,
                            `neighborhood_comments`,
                            `utilities_included`,
                            `expences`,
                            `builiding_office_hours`,
                            `date_added`
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $query->execute([
                            $address,
                            $type,
                            $addr1,
                            $addr2,
                            $buildingName,
                            $contactPhone,
                            $latitude,
                            $longitude,
                            $lastUpdated,
                            $propertyInfo,
                            $amenitiesList,
                            $studentFeatures,
                            $appartmentFeatures,
                            $contactPerson,
                            $buildingDesc,
                            $walkScore,
                            $transitScore,
                            $uri,
                            $city,
                            $zip5Code,
                            $state,
                            $images,
                            $nearbyColleges,
                            $nearbyTransit,
                            $nearbyRail,
                            $nearbyShopping,
                            $nearbyParks,
                            $nearbyAirports,
                            $neighborhoodComments,
                            $utilsIncluded,
                            $expences,
                            $officeHours,
                            $date,
                        ]);

                        // paste avialability
                        $query = $db->pdo->prepare("SELECT `id` FROM `properties` WHERE `link` = ? LIMIT 1");
                        $query->execute([$uri]);
                        $prop = $query->fetch();

                        if ($prop->id) {
                            $propId = $prop->id;
                            foreach ($availability as $item) {
                                $query = $db->pdo->prepare("INSERT INTO `availability` (
                                    `property_id`,
                                    `bedroom_cnt`,
                                    `bathroom_cnt`,
                                    `listing_price`,
                                    `home_size_sq_ft`,
                                    `lease_length`,
                                    `status`,
                                    `image_urls`
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $query->execute([
                                    $propId,
                                    $item['bedroom_cnt'],
                                    $item['bathroom_cnt'],
                                    $item['listing_price'],
                                    $item['home_size_sq_ft'],
                                    $item['lease_length'],
                                    $item['status'],
                                    $item['image_urls']
                                ]);
                            }
                        }
                    }
                }
            } elseif ($method === 'update') {
                $db = new MySQL('parsing','local');
                $date = date('Y-m-d H:i:s');
                $query = $db->pdo->prepare("UPDATE `properties` SET `last_update` = ?, is_deleted = ? WHERE `link` = ?");
                $query->execute([$date, 0, $uri]);
            }
        } else {
            if ($method === 'update') {
                $db = new MySQL('parsing','local');
                $date = date('Y-m-d H:i:s');
                $query = $db->pdo->prepare("UPDATE `properties` SET `last_update` = ?, is_deleted = ? WHERE `link` = ?");
                $query->execute([$date, 1, $uri]);
            } else {
                $task = '{"link":"' . $uri . '", "method":"' . $method . '", "by":"' . $by . '"}';
                Redis::init()->rpush('tasks', $task);
            }
        }
    }

    /**
     * Add log for parsing process.
     *
     * @param Exception|JsonException $e
     */
    protected static function log($e): void
    {
        $message = '[' . date('Y-m-d H:i:s') . '] Msg: ' . $e->getMessage() . "\n";
        $message .= 'File: ' . $e->getFile() . '; Line: ' . $e->getLine() . "\n";

        file_put_contents(LOG_DIR . '/parse-log.out', $message, FILE_APPEND);
    }

    protected function clearText($text)
    {
        $text = preg_replace('/(?:&nbsp;|\h)+/u', ' ', $text);
        $text = preg_replace('/\h*(\R)\s*/u', '$1', $text);
        $text = trim($text);

        return $text;
    }

    protected function getNearbyData($elem, $timeType)
    {
        $nearbyData = [];
        foreach ($elem as $nrb) {
            $nrb = $nrb->find("td");
            if (count($nrb) >= 3) {
                $name = $this->clearText($nrb[0]->text());
                $time = $this->clearText($nrb[1]->text());
                $distance = $this->clearText($nrb[2]->text());
                $nearbyData[] = [
                    'name' => $name,
                    $timeType => $time,
                    'distance' => $distance
                ];
            }
        }
        return !empty($nearbyData) ? json_encode($nearbyData, JSON_PRETTY_PRINT) : '';
    }

    /**
     * Parsing amenities block
     *
     * @param  Document $specGroups
     * @return json
     */
    protected function amenitiesBlock($specGroups)
    {
        $amenitiesList = [];
        foreach ($specGroups as $group) {
            $title = isset($group->find('h3.specGroupName')[0]) ?
                $this->clearText($group->find('h3.specGroupName')[0]->text()) : '';
            $amenities = $group->find('li');
            foreach ($amenities as $amenity) {
                $amenitiesList[$title][] = $this->clearText($amenity->text());
            }
        }

        return json_encode($amenitiesList, JSON_PRETTY_PRINT);
    }
}