<?php
include 'get_product.php';

$method = $_SERVER['REQUEST_METHOD'];

// Process only when method is POST
if($method == 'POST'){
    $requestBody = file_get_contents('php://input');
    $json = json_decode($requestBody);

    $action = $json->result->action;

    $context = FALSE;
    $category = $json->result->parameters->category;


    // check if user already exists else create a new one
    $fid = $json->originalRequest->data->sender->id;

    create_user($fid);

    //get the request json string (only for debugging)
    $fp = fopen('results.json', 'w');
    fwrite($fp, json_encode($json));
    fclose($fp);


    $debugger = "no";

    switch (strtolower($action)) {
        // get products via product name
        case 'product.ask.name':
            $product_name = $json->result->parameters->product_name;
            if($product_name == "") {
                $product_name = $json->result->parameters->product_name_selected;
            }


            $result = get_product($product_name);
            if (count($result[0]) != 0) {
                $speech = $result[0]['product_name'] . " kostet " . $result[0]['price'] . "€. Möchtest du das kaufen?";
            } else {
                $speech = "Ich konnte dieses Produkt leider nicht finden. 😓";
            }

            $context = array(['name' => 'products', 'parameters' => $result[0], 'lifespan' => 2]);
            break;

        // add products to the cart
        case 'productaskname.productaskname-yes':
            $followup = array('name' => 'product_amount');
            break;

        case 'productaskname.productaskname-amount':
            $idproduct = null;
            $price = null;

            $contexts = $json->result->contexts;

            foreach ($contexts as $key=>$con) {
                if($con->name == "products"){
                    $idproduct = $con->parameters->idproduct;
                    $price = $con->parameters->price;
                    update_address_sid($aid, $sid);
                }
            }

            $amount = $json->result->parameters->amount;
            $fid = $json->originalRequest->data->sender->id;

            // TODO $sId = $json->sessionId;

            // get data for the product
            $result = add_to_cart($idproduct, $fid, $amount, $price);
            $speech = $result;
            break;

        // -------------------------
        // CART INFO
        // -------------------------
        case 'cart.info':
            $fid = $json->originalRequest->data->sender->id;

            $result = get_cart($fid);
            $total_price = 0;

            if (count($result) === 0) {
                $speech = "Du hast noch keine Produkte in deinem Einkaufskorb. 🤷‍";
            } elseif (count($result) === 1) {
                $speech = "Es befindet sich ein Produkt in deinem Einkaufskorb 🛒\n\n";
                $speech .= $result[0]['product_name'] . " x " . $result[0]['quantity'] . " zu je " . $result[0]['price'] . "€\n";
                $total_price += $result[0]['quantity'] * $result[0]['price'];
                $speech .= "\nDie aktuelle Summe beträgt " . $total_price . "€\n";
                $speech .= "Ist das alles? 🤗";
            } else {
                $speech = "Es befinden sich " . count($result) . " Produkte in deinem Einkaufskorb 🛒:\n\n";
                for ( $i = 0; $i < count($result); $i++) {
                    $speech .= $i+1 . ": " . $result[$i]['product_name'] . " x " . $result[$i]['quantity'] . " zu je " . $result[$i]['price'] . "€ \n";
                    $total_price += $result[$i]['quantity'] * $result[$i]['price'];
                }
                $speech .= "\nDie aktuelle Zwischensumme beträgt " . $total_price . "€\n";
                $speech .= "Ist das alles? 🤗";
            }
            break;

        case 'cartinfo.cartinfo-yes':
            $speech = "NICE";
            $followup = array('name' => 'payment_check');
            break;

        case 'cart.delete.entry':
            $fid = $json->originalRequest->data->sender->id;
            $product_name = $json->result->parameters->product_name;
            $speech = delete_cart_entry($fid, $product_name);
            break;

        case 'cartdelete.cartdelete-yes':
            $fid = $json->originalRequest->data->sender->id;
            $speech = delete_cart($fid);
            break;

        // -------------------------
        // Payment
        // -------------------------
        case 'payment.check':
            $fid = $json->originalRequest->data->sender->id;
            $sid = $json->sessionId;

            $payment = get_payment_info($fid);

            if (count($payment) === 0) {
                $followup = array('name' => 'payment_info');
            } else {
                $speech = "Möchtest du die Rechnung mit " . $payment[0][0] . ": " . $payment[0][1] . " begleichen? 💰";
                $context = array(['name' => 'pid', 'parameters' => array("pid" => $payment[0][3]), 'lifespan' => 3]);
            }
            foreach ($payment as $key=>$pay) {
                if($pay[2] == $sid) {
                    $followup = array('name' => 'billing_check');
                }
            }

            // $debugger = $payment;
            break;

        case 'paymentcheck.paymentcheck-yes';
            $sid = $json->sessionId;
            $contexts = $json->result->contexts;

            foreach ($contexts as $key=>$con) {
                if($con->name == "pid"){
                    $pid = $con->parameters->pid;
                    update_payment_sid($pid, $sid);
                }
            }

            $followup = array('name' => 'payment_check');
            break;

        case 'paymentcheck.paymentcheck-no';
            $followup = array('name' => 'payment_info');
            break;

        case 'payment.info':
            $fid = $json->originalRequest->data->sender->id;
            $ptype = $json->result->parameters->paymenttype;
            $pstring = $json->result->parameters->paymentstring;
            $sid = $json->sessionId;

            if(isset($pstring) && isset($ptype)) {
                set_payment_info($fid, $ptype, $pstring, $sid);
            }
            $followup = array('name' => 'payment_check');

            break;

        // -------------------------
        // ADDRESS
        // -------------------------

        case 'billing.address.check':
            $fid = $json->originalRequest->data->sender->id;
            $sid = $json->sessionId;

            $addresses = get_address($fid);

            if (count($addresses) === 0) {
                $followup = array('name' => 'billing_address');
            } else {
                $city = get_city($addresses[0][2]);
                $debugger = $addresses[0][2];
                $speech = "Möchtest du die Adresse " . $addresses[0][1] . ", " . $city[0][0] . " " . $city[0][1] . " wählen?"; //TODO: JA Option hinzufügen
                $context = array(['name' => 'aid', 'parameters' => array("aid" => $addresses[0][0]), 'lifespan' => 3]);
            }
            foreach ($addresses as $key=>$address) {
                if($address["sid"] == $sid) {
                    $city = get_city($addresses[$key][2]);

                    $speech = "Deine Waren werden in Kürze versendet! 📦😍";
                    //$followup = array('name' => 'billing_address');
                    $fid = $json->originalRequest->data->sender->id;
                    delete_cart($fid);
                }
            }

            break;

        case 'billingaddresscheck.billingaddresscheck-yes';
            $sid = $json->sessionId;
            $contexts = $json->result->contexts;

            foreach ($contexts as $key=>$con) {
                if($con->name == "aid"){
                    $aid = $con->parameters->aid;
                    update_address_sid($aid, $sid);
                }
            }

            $speech = "chill";
            $followup = array('name' => 'billing_check');
            break;

        case 'billingaddresscheck.billingaddresscheck-no';
            $followup = array('name' => 'billing_address');
            break;

        case 'billing.address':
            $fid = $json->originalRequest->data->sender->id;
            $sid = $json->sessionId;

            $street_address = $json->result->parameters->{'street-address'};
            $geo_city = $json->result->parameters->{'geo-city'};
            $zip_code = $json->result->parameters->{'zip-code'};
            $geo_country = $json->result->parameters->{'geo-country'};

            $speech = billing_address($fid, $street_address, $zip_code, $geo_city, $geo_country, $sid);

            $followup = array('name' => 'billing_check');

            //$speech = $street_address . " - " . $geo_city . " - " . $geo_country . " - " . $zip_code . " - ";
            break;

        // -------------------------
        // Send Products
        // -------------------------
        case 'send.products':
            $fid = $json->originalRequest->data->sender->id;
            $product_name = $json->result->parameters->product_name;
            $speech = delete_cart_entry($fid, $product_name);
            break;
// DONE
        case 'product.ask.details':
            $subcategory = $json->result->parameters->detial_subcategory;
            if($subcategory == "") {
                $subcategory = $json->result->parameters->detial_subcategory_selected;
            }

            $debugger = $subcategory;
            $result = get_product_by_details($subcategory);

            if (count($result) !== 0) {
                $speech = "Ich hab " . count($result) . " verschiedene Produkte in dieser Kategorie gefunden. \n";
                for ( $i = 0; $i < count($result); $i++) {
                    $speech .= utf8_encode($i+1 . ": " . $result[$i]['product_name'] . " um " . $result[$i]['price']) . "€ \n";
                }
            } else {
                $speech = "Konnte leider keine Produkte in dieser Kategorie finden, sorry. 😓";
            }

            $context = array();

            foreach ($result as $key=>$product) {
                //$context .= ['name' => $product['product_name'], 'lifespan' => 2, 'parameters' => $product];
                $product = array_map("utf8_encode", $product );
                $name = "prod" . $key;
                array_push($context, array('name' => $name, 'lifespan' => 3, 'parameters' => $product));
            }
            break;

        case 'productaskdetails.productaskdetails-selectnumber':
            $detailcategories = $json->result->contexts;
            $number = $json->result->parameters->number;
            $detailcat = null;
            $name = "prod" . ($number[0]-1);

            foreach ($detailcategories as $key=>$category) {
                if($category->name == $name){
                    $speech = $category->parameters->product_name . " - ";
                    $detailcat = $category->parameters->product_name;
                    $speech .= $category->parameters->idproduct;
                }
            }
            $followup = array('name' => 'product_ask_name', 'data' => array('pname' => $detailcat));
            break;
// DONE
        case 'product.ask.subcategory':
            $subcategory = $json->result->parameters->product_subcategory;
            if($subcategory == "") {
                $subcategory = $json->result->parameters->product_subcategory_selected;
            }

            $result = get_details_by_sub($subcategory);

            if (count($result) !== 0) {
                $speech = "Ich habe " . count($result) . " verschiedene Unterkategorien in dieser Kategorie gefunden: \n";
                for ( $i = 0; $i < count($result); $i++) {
                    $speech .= utf8_encode($i+1 . ": " . $result[$i][1] . " \n");
                }
            } else {
                $speech = "Es konnten leider keine Vorschläge in dieser Kategorie gefunden werden 😓";
            }

            $context = array();

            foreach ($result as $key=>$product) {
                //$context .= ['name' => $product['product_name'], 'lifespan' => 2, 'parameters' => $product];
                $product = array_map("utf8_encode", $product );
                $name = "productcat" . $key;
                array_push($context, array('name' => $name, 'lifespan' => 3, 'parameters' => $product));
            }

            // $context = array(['name' => 'products', 'parameters' => $result[0], 'lifespan' => 2]);

            break;

        case 'productasksubcategory.productasksubcategory-selectnumber':
            $detailcategories = $json->result->contexts;
            $number = $json->result->parameters->number;
            $detailcat = null;
            $name = "productcat" . ($number[0]-1);

            foreach ($detailcategories as $key=>$category) {
                if($category->name == $name){
                    $speech = $category->parameters->name_detail_subcategory . " - ";
                    $detailcat = $category->parameters->name_detail_subcategory;
                    $speech .= $category->parameters->iddetail_subcategory;
                }
            }

            $followup = array('name' => 'product_detail', 'data' => array('detail' => $detailcat));
            break;
// DONE
        case 'product.ask.category':
            $category = $json->result->parameters->product_category;
            $result = get_sub_by_cat($category);

            if (count($result) !== 0) {
                $speech = "Ich hab " . count($result) . " verschiedene Unterkategorien in dieser Kategorie gefunden 😊: \n";
                for ( $i = 0; $i < count($result); $i++) {
                    $speech .= utf8_encode($i+1 . ": " . $result[$i][1] . " \n");
                }
            } else {
                $speech = "Es konnten leider keine Vorschläge in dieser Kategorie gefunden werden. 😓";
            }

            $context = array    ();
            foreach ($result as $key=>$product) {
                //$context .= ['name' => $product['product_name'], 'lifespan' => 2, 'parameters' => $product];
                $product = array_map("utf8_encode", $product );
                $name = 'subcat' . $key;
                array_push($context, array('name' => $name, 'lifespan' => 1, 'parameters' => $product));
            }

            // $context = utf8_encode($context[1]['name']);
            break;

        // product.ask.detail für category
        case 'productaskcategory.productaskcategory-selectnumber':
            $subcategories = $json->result->contexts;
            $number = $json->result->parameters->number;
            $subcat = null;
            $name = "subcat" . ($number[0]-1);
            foreach ($subcategories as $key=>$category) {
                if($category->name == $name){
                    $subcat = $category->parameters->product_subcategory_name;
                }
            }
            $followup = array('name' => 'product_subcategory', 'data' => array('subcat' => $subcat));
            break;
        case 'test':
            $followup = array('name' => 'test_test', 'data' => array('number' => 25));
            $speech = "nice";
            break;

        default:
            //$followup = array('name' => 'fallback');
    }

    $response = new \stdClass();
    $response->speech = $speech;

    $response->debugger = $debugger;
    $response->displayText = $speech;
    $response->source = "webhook";
    if ($messages) {
        $response->messages = $messages;
    }
    if ($context) {
        $response->contextOut = $context;
    }
    if (isset($followup)) {
        $response->followupEvent = $followup;
    }
    if ($event) {
        $response->followupEvent->name = $event;
        $response->followupEvent->data->userID = $data;
    }
    echo json_encode($response);
}
else
{
    echo "Method not allowed";
}

?>