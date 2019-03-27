<?php
/**
 * Created by PhpStorm.
 * User: Ronald
 * Date: 21.11.2017
 * Time: 00:09
 */

$servername = "localhost";
$username = "chatbot";
$password = "oJnkOeKxLi87H?!IdFo.";
$dbname = "chatbot";

// Create connection
$pdo = new PDO('mysql:host=' . $servername . ';dbname=' . $dbname, $username, $password);

// Checks if the user exists otherwise he will be created
function create_user($uid)
{
    global $pdo;
    $statement = $pdo->prepare("SELECT iduser  FROM user WHERE fb_id = :uid");
    $statement->execute(array(':uid' => $uid));

    if ($statement->rowCount() === 0) {
        $statement = $pdo->prepare("INSERT INTO user SET `fb_id` = :uid");
        $statement->execute(array(':uid' => $uid));
    }
}

function get_product($product_name)
{
    global $pdo;
    $results = [];

    $statement = $pdo->prepare("SELECT idproduct, product_name, price  FROM product WHERE UPPER( product_name ) = UPPER( :pname )");
    $statement->execute(array(':pname' => $product_name));

    if ($statement->rowCount() > 0) {
        while ($row = $statement->fetch()) {
            array_push($results, array('idproduct' => $row["idproduct"], 'product_name' => $row["product_name"], 'price' => $row["price"]));
        }
        $pdo = null;
        return $results;
    } else {
        $pdo = null;
        return FALSE;
    }
}

function get_product_by_details($category_name){
    global $pdo;
    $results = [];

    // get the details id
    $statement = $pdo->prepare("SELECT iddetail_subcategory FROM detail_subcategory WHERE UPPER( name_detail_subcategory ) = UPPER( :cname )");
    $statement->execute(array(':cname' => $category_name));
    $sub_id = $statement->fetchAll()[0]['iddetail_subcategory'];

    // get products with this subid
    $statement = $pdo->prepare("SELECT idproduct, product_name, price  FROM product WHERE detail_subcategory_iddetail_subcategory = :cid");
    $statement->execute(array(':cid' => $sub_id));
    $result = $statement->fetchAll();

    return $result;
}

// get the productlist for a sertain detail
function get_details_by_sub($category_name){
    global $pdo;
    $results = [];

    // get the subcategory id
    $statement = $pdo->prepare("SELECT idproduct_subcategory FROM product_subcategory WHERE UPPER( product_subcategory_name ) = UPPER( :cname )");
    $statement->execute(array(':cname' => $category_name));
    $sub_id = $statement->fetchAll()[0]['idproduct_subcategory'];

    //return $sub_id;

    // get details with this subid
    $statement = $pdo->prepare("SELECT iddetail_subcategory, name_detail_subcategory FROM detail_subcategory WHERE product_subcategory_idproduct_subcategory = :cid");
    $statement->execute(array(':cid' => $sub_id));
    $result = $statement->fetchAll();

    return $result;
}

function get_sub_by_cat($category_name){
    global $pdo;
    $results = [];

    // get the subcategory id
    $statement = $pdo->prepare("SELECT idproduct_category FROM product_category WHERE UPPER( product_category_name ) = UPPER( :cname )");
    $statement->execute(array(':cname' => $category_name));
    $cat_id = $statement->fetchAll()[0]['idproduct_category'];

    //return $sub_id;

    // get details with this subid
    $statement = $pdo->prepare("SELECT idproduct_subcategory, product_subcategory_name FROM product_subcategory WHERE product_category_idproduct_category = :cid");
    $statement->execute(array(':cid' => $cat_id));
    $result = $statement->fetchAll();

    return $result;
}

function get_category($category_name){
    /*
     * SELECT product.product_name
        FROM
          product
 
        JOIN detail_subcategory ON detail_subcategory.iddetail_subcategory = product.detail_subcategory_iddetail_subcategory
        JOIN product_subcategory ON detail_subcategory.product_subcategory_idproduct_subcategory = product_subcategory.idproduct_subcategory
        JOIN product_category ON product_category.idproduct_category = product_subcategory.product_category_idproduct_category
 
        WHERE
          product_category.product_category_name = 'Chips'
          OR
          product_subcategory.product_subcategory_name = 'Chips'
          OR
          detail_subcategory.name_detail_subcategory = 'Chips'
     */
    global $pdo;
    $results =[];

    $statement = $pdo->prepare("SELECT product.idproduct, product.product_name, product.price
                                            FROM
                                              product
                                     
                                            JOIN detail_subcategory ON detail_subcategory.iddetail_subcategory = product.detail_subcategory_iddetail_subcategory
                                            JOIN product_subcategory ON detail_subcategory.product_subcategory_idproduct_subcategory = product_subcategory.idproduct_subcategory
                                            JOIN product_category ON product_category.idproduct_category = product_subcategory.product_category_idproduct_category
                                     
                                            WHERE
                                              product_category.product_category_name = :cname
                                              OR
                                              product_subcategory.product_subcategory_name = :cname
                                              OR
                                              detail_subcategory.name_detail_subcategory = :cname");
    return $statement->execute(array(':cname' => $category_name)) ? TRUE : FALSE;

    if ($statement->rowCount() > 0) {
        while ($row = $statement->fetch()) {
            array_push($results, array('idproduct' => $row["idproduct"], 'product_name' => $row["product_name"], 'price' => $row["price"]));
        }
        $pdo = null;
        return $results;
    } else {
        $pdo = null;
        return FALSE;
    }

}

function add_to_cart($pid, $fid, $amount, $price)
{
    global $pdo;

    // get the userID of the user
    $uid = get_userid($fid);


    // get the stock of the product
    $statement = $pdo->prepare("SELECT stock  FROM product WHERE idproduct = :pid");
    $statement->execute(array(':pid' => $pid));
    $stock = $statement->fetchAll()[0]['stock'];

    // stock >= amount
    if ($stock >= $amount) {
        // check if order already exists
        $statement = $pdo->prepare("SELECT `idorders` FROM orders WHERE `user_iduser` = :uid AND `status` = 0");
        $statement->execute(array(':uid' => $uid));
        $idorders = $statement->fetchAll()[0]['idorders'];

        // wenn noch nicht vorhanden wird es neu angelegt
        if ($statement->rowCount() === 0) {
            // neue orders anlegen
            $statement = $pdo->prepare("INSERT INTO orders SET user_iduser = :uid");
            $statement->execute(array(':uid' => $uid));

            $statement = $pdo->prepare("SELECT `idorders` FROM orders WHERE `user_iduser` = :uid AND `status` = 0");
            $statement->execute(array(':uid' => $uid));

            $idorders = $statement->fetchAll()[0]['idorders'];
        }

        // check if order_item already exists
        $statement = $pdo->prepare("SELECT `orders_idorders` FROM order_item WHERE `orders_idorders` = :oid AND `product_idproduct` = :pid");
        $statement->execute(array(':oid' => $idorders, ':pid' => $pid));

        if ($statement->rowCount() === 0) {
            // daten in die db einfügen
            $statement = $pdo->prepare("INSERT INTO order_item SET orders_idorders = :oid, product_idproduct = :pid, quantity = :amount, price = :price");
            $statement->execute(array(':pid' => $pid, ':price' => $price, ':amount' => $amount, ':oid' => $idorders));

            return "Das Produkt wurde in den Warenkorb gelegt.";

        } else {
            // daten updaten
            $statement = $pdo->prepare("UPDATE order_item SET quantity = :amount, price = :price WHERE `orders_idorders` = :oid AND `product_idproduct` = :pid");
            $statement->execute(array(':amount' => $amount, ':price' => $price, ':oid' => $idorders, ':pid' => $pid));

            return "Die Menge wurde auf " . $amount . " geändert! 👍";
        }
    } else {
        return "Wir haben leider nur noch " . $stock . " Stück auf Lager 😓";
    }
}

function get_cart($fid)
{
    global $pdo;

    // get the userID of the user
    $uid = get_userid($fid);

    $statement = $pdo->prepare("SELECT 
                                                order_item.quantity, 
                                                product.product_name, 
                                                product.price
                                            FROM 
                                                order_item 
                                                JOIN product ON product.idproduct = order_item.product_idproduct
                                                JOIN orders ON orders.idorders = order_item.orders_idorders
                                            WHERE 
                                                orders.user_iduser = :uid");
    $statement->execute(array(':uid' => $uid));
    $res = $statement->fetchAll();

    return $res;
}

// delete one entry from the "order_item" list
function delete_cart_entry($fid, $pname)
{
    global $pdo;

    // get the ordersID
    $statement = $pdo->prepare("SELECT orders.idorders 
                                            FROM 
                                              user 
                                            JOIN orders ON orders.user_iduser = user.iduser 
                                            WHERE 
                                              user.fb_id = :fid");
    $statement->execute(array(':fid' => $fid));
    $oid = $statement->fetchAll()[0]['idorders'];



    // get the productID of the product
    $statement = $pdo->prepare("SELECT idproduct FROM product WHERE product_name = :pname");
    $statement->execute(array(':pname' => $pname));
    $pid = $statement->fetchAll()[0]['idproduct'];

    $statement = $pdo->prepare("DELETE FROM order_item WHERE orders_idorders = :oid AND product_idproduct = :pid");
    $statement->execute(array(':oid' => $oid, ':pid' => $pid));

    return "Das Produkt wurde erfolgreich aus dem Warenkorb entfernt. 😊";
}

function delete_cart($fid)
{
    global $pdo;

    // get the ordersID
    $statement = $pdo->prepare("SELECT orders.idorders 
                                            FROM 
                                              user 
                                            JOIN orders ON orders.user_iduser = user.iduser 
                                            WHERE 
                                              user.fb_id = :fid");
    $statement->execute(array(':fid' => $fid));
    $oid = $statement->fetchAll()[0]['idorders'];

    $statement = $pdo->prepare("DELETE FROM order_item WHERE orders_idorders = :oid");
    $statement->execute(array(':oid' => $oid));

    return "Der Warenkorb wurde geleert.";
}

function get_payment_info($fid)
{
    global $pdo;

    // get the userID of the user
    $uid = get_userid($fid);
    //INSERT INTO payment SET `user_iduser` = 33, paymenttype = 'Kreditkarte', `payment_string1` = 'AT 1234'

    $statement = $pdo->prepare("SELECT paymenttype, payment_string1, payment_string2, idpayment FROM payment WHERE `user_iduser` = :uid");
    $statement->execute(array(':uid' => $uid));
    $res = $statement->fetchAll();

    return $res;
}

// sets the paymentinfo
// needs the uid to see if this already exists
function set_payment_info($fid, $ptype, $pstring, $sid)
{
    global $pdo;

    // get the userID of the user
    $uid = get_userid($fid);

    //INSERT INTO payment SET `user_iduser` = 33, paymenttype = 'Kreditkarte', `payment_string1` = 'AT 1234'
    $statement = $pdo->prepare("INSERT INTO payment SET `user_iduser` = :uid, paymenttype = :ptype, payment_string1 = :pstring, payment_string2 = :sid");
    $statement->execute(array(':uid' => $uid, ':ptype' => $ptype, ':pstring' => $pstring, ':sid' => $sid));
    $res = $statement->fetchAll();

    return $res;
}

function update_payment_sid($pid, $sid) {
    global $pdo;

    //INSERT INTO payment SET `user_iduser` = 33, paymenttype = 'Kreditkarte', `payment_string1` = 'AT 1234'

    $statement = $pdo->prepare("UPDATE `payment` SET `payment_string2` = :sid WHERE `idpayment` = :pid");
    $statement->execute(array(':sid' => $sid, ':pid' => $pid));
    //$res = $statement->fetchAll();

    return TRUE;
}


function billing_address($fid, $street, $zip, $city_name, $country_name, $sid) {
    global $pdo;
    $new = FALSE;

    // get the userID of the user
    $uid = get_userid($fid);

    // get all the address for this user
    // $current_addresses = get_address($fid);

    // -- COUNTRY --
    // check if country exists (get country_id) else insert new country
    $statement = $pdo->prepare("SELECT `idcountry` FROM country WHERE `name` = :country_name");
    $statement->execute(array(':country_name' => $country_name));
    $id_country = $statement->fetchAll()[0]['idcountry'];

    // wenn noch nicht vorhanden wird es neu angelegt
    if ($statement->rowCount() === 0) {
        // neue orders anlegen
        $statement = $pdo->prepare("INSERT INTO country SET `name` = :country_name");
        $statement->execute(array(':country_name' => $country_name));

        // get the id of the new inserted country
        $statement = $pdo->prepare("SELECT `idcountry` FROM country WHERE `name` = :country_name");
        $statement->execute(array(':country_name' => $country_name));

        $id_country = $statement->fetchAll()[0]['idcountry'];
        $new = TRUE;
    }


    // -- CITY --
    // check if city exists (get country_id) else insert new city
    $statement = $pdo->prepare("SELECT `idcity` FROM city WHERE `PLZ` = :zip AND `name` = :city_name AND country_idcountry = :id_country");
    $statement->execute(array(':zip' => $zip, ':city_name' => $city_name, ':id_country' => $id_country));
    $id_city = $statement->fetchAll()[0]['idcity'];


    // wenn noch nicht vorhanden wird es neu angelegt
    if ($statement->rowCount() === 0) {
        // neue city anlegen
        $statement = $pdo->prepare("INSERT INTO city SET `name` = :city_name, PLZ = :zip, country_idcountry = :id_country");
        $statement->execute(array(':city_name' => $city_name, ':zip' => $zip, ':id_country' => $id_country));

        // get the id of the new inserted country
        $statement = $pdo->prepare("SELECT `idcity` FROM city WHERE `PLZ` = :zip AND `name` = :city_name AND country_idcountry = :id_country");
        $statement->execute(array(':zip' => $zip, ':city_name' => $city_name, ':id_country' => $id_country));
        $id_city = $statement->fetchAll()[0]['idcity'];
        $new = TRUE;

    }

    // -- ADDRESS --
    // check if address exists (get country_id) else insert new address
    $statement = $pdo->prepare("SELECT `idaddress` FROM address WHERE `street` = :street AND `city_idcity` = :id_city AND user_iduser = :uid");
    $statement->execute(array(':street' => $street, ':id_city' => $id_city, ':uid' => $uid));

    // wenn noch nicht vorhanden wird es neu angelegt
    if ($statement->rowCount() === 0) {
        // neue address anlegen
        $statement = $pdo->prepare("INSERT INTO address SET `street` = :street, user_iduser = :uid, `city_idcity` = :id_city, `sid` = :sid");
        $statement->execute(array(':street' => $street, ':uid' => $uid, ':id_city' => $id_city, ':sid' => $sid));
        $new = TRUE;
    }
    return $new ? "Die Adresse wurde neu hinzugefügt ✔" : "Die Adresse wurde gewählt ✔";
}


// get address
function get_address($fid) {
    global $pdo;

    // get the userID of the user
    $uid = get_userid($fid);

    // get the street
    $statement = $pdo->prepare("SELECT idaddress, street, city_idcity, sid FROM address WHERE user_iduser = :uid");
    $statement->execute(array(':uid' => $uid));

    return $statement->fetchAll();
}

function update_address_sid($aid, $sid) {
    global $pdo;

    // get the street
    $statement = $pdo->prepare("UPDATE `address` SET `sid` = :sid WHERE `idaddress` = :aid");
    $statement->execute(array(':sid' => $sid, ':aid' => $aid));

    return TRUE;
}

function get_city($cityid) {
    global $pdo;

    // get the city and the zip-code
    $statement = $pdo->prepare("SELECT PLZ, name, country_idcountry FROM city WHERE idcity = :cid");
    $statement->execute(array(':cid' => $cityid));

    return $statement->fetchAll();

}


// get user id with the help of the facebookID
function get_userid($fid) {
    global $pdo;

    // get the userID of the user
    $statement = $pdo->prepare("SELECT iduser  FROM user WHERE fb_id = :fid");
    $statement->execute(array(':fid' => $fid));
    return $statement->fetchAll()[0]['iduser'];
}

function send_products($fid) {
    global $pdo;

    $uid = get_userid($fid);

    // update the status od the order
    $statement = $pdo->prepare("UPDATE `orders` SET `status` = 1 WHERE `user_iduser` = :uid");
    $statement->execute(array(':uid' => $uid));

    return TRUE;
}