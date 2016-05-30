<?php
include 'db.php';
require 'Slim/Slim.php';
session_start();

header("Access-Control-Allow-Origin: http://127.0.0.1");
//header("Access-Control-Allow-Origin: http://127.0.0.1:8888");
header("Access-Control-Allow-Origin: *");

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// Fonction à appeler pour valider le login -> Virer la fonction de conversion et virer les chaines de caractere dans le sql. Faire comme getLastReview
$app->post('/login', 'login');
// Liste complète des WC
$app->get('/wc','getWC');
// 2500 premiers WC pour le script d'enrichissement des adresses
$app->get('/wc2500','get2500WC');
// Fonction pour poster une review sur les wc. NB : Pas de distinction sur les review et les notes. Si l'user poste seulement une note on mettra une chaine vide dans la ligne de la table
$app->post('/insertreview','insertReview');
// Fonction pour ajouter un Wc dans la base de données par l'utilisateur
$app->post('/insertwc','insertWc');
// Fonction pour mettre à jour le profil de l'utlisateur
$app->put('/updateuser/:id', 'updateUser');
// Fonction pour récupérer les dernières review de l'utilisateur
$app->get('/lastreview/:id','getLastReview');
// Fonction pour récupérer les infos d'un wc à partir d'un id donné
$app->get('/wcinfo/:id','getWcInfo');
// Fonction pour get les review
$app->get('/wcreview/:id','getWcReview');
// Fonction pour get les places autour de l'utilisateur. A faire : nouveau google token , différent de celui utilisé par wirecard
$app->get('/gplaces/:latitude/:longitude','getGPlaces');
// Fonction pour get les places autour de l'utilisateur au dela de la première page avec le nouveau token
$app->get('/gplaces/:latitude/:longitude/:token','getGPlacesToken');
// Fonction pour mettre à jour la note
$app->get('/wcNote/:id','getWcNote');



// Lancement de l'app généré par Slim
$app->run();

function login() {
    $app = \Slim\Slim::getInstance();
    $request = $app->request();
    parse_str($request->getBody(),$user);


    // on encode, décode pour pouvoir utiliser la notation fléchée
    $user = json_encode($user);
    $user = json_decode($user);

    try 
    {

        if($user->login == ""){
        	echo '{"error":{"text":email vide}}';
            return;
        }
        if($user->mdp== ""){
        	echo '{"error":{"text":password vide}}';
            return;
        }
        $sql = "SELECT * from user where email = :email and password = :password";
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("email", $user->login);
        $stmt->bindParam("password", $user->mdp);
        $stmt->execute();
        $res = $stmt->fetchObject();
        $db = null;
       

        // On vérifie que la fonction a bien renvoyé un résultat
		if (gettype($res) == 'object'){
			$user_id = $res->user_id;

			echo '{"loginStatus":{"status": "true","user_id" : "'.$user_id.'"}}';
		}
		else{
			echo '{"loginStatus":{"status": "false"}}';
		}
    } catch(PDOException $e) {
        $app->response()->setStatus(404);
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function getWC() {
	$sql = "SELECT v.WC_id, v.wc_name, v.address, v.type, v.latitude, v.longitude, IFNULL(sum(v.note) / count(*),'undef') as note from(SELECT wc.WC_id, wc.wc_name, wc.address, wc.type,wc.latitude, wc.longitude, review.note FROM wc LEFT JOIN review ON wc.WC_id = review.WC_id UNION SELECT wc.WC_id, wc.wc_name, wc.address, wc.type, wc.latitude, wc.longitude, review.note FROM wc RIGHT JOIN review ON wc.WC_id = review.WC_id) v group by v.WC_id, v.wc_name, v.address, v.type, v.latitude, v.longitude";
	try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $wc = $stmt->fetchAll();
        $db = null;

        echo '{"wc": ' . json_encode(utf8ize($wc)) . '}';
	} catch(PDOException $e) {
	    //error_log($e->getMessage(), 3, '/var/tmp/php.log');
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function get2500WC() {
	$sql = "SELECT * from wc where adress is null and latitude is not null and longitude is not null LIMIT 2500";
	
	try {
		$db = getDB();
		$stmt = $db->query($sql);  
		$wc = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		//echo '{"wc": ' . json_encode($wc) . '}';
		echo json_encode($wc);
	} catch(PDOException $e) {
	    //error_log($e->getMessage(), 3, '/var/tmp/php.log');
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}


function insertReview() {
    $app = \Slim\Slim::getInstance();
    $request = $app->request();
    parse_str($request->getBody(),$review);


    $sql = "INSERT INTO review (user_id, wc_id, note, comment) VALUES (:user_id, :wc_id, :note, :comment)";
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("user_id", $review['user_id']);
        $stmt->bindParam("wc_id", $review['wc_id']);
        $stmt->bindParam("note", $review['note']);
        $stmt->bindParam("comment", $review['comment']);
        $stmt->execute();
        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}


function insertWc() {
    $app = \Slim\Slim::getInstance();
    $request = $app->request();
    parse_str($request->getBody(),$wc);

    $sql = "INSERT INTO wc (src_id, description, wc_cnt, address, type, prix, latitude, longitude, wc_name) VALUES (:src_id, :description, :wc_cnt, :address, :type, :prix, :latitude, :longitude, :wc_name)";
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("src_id", urldecode($wc['src_id']));
        $stmt->bindParam("description", urldecode($wc['description']));
        $stmt->bindParam("wc_cnt", urldecode($wc['wc_cnt']));
        $stmt->bindParam("address", urldecode($wc['address']));
        $stmt->bindParam("type", urldecode($wc['type']));
        $stmt->bindParam("prix", urldecode($wc['prix']));
        $stmt->bindParam("latitude", urldecode($wc['latitude']));
        $stmt->bindParam("longitude", urldecode($wc['longitude']));
        $stmt->bindParam("wc_name", urldecode($wc['wc_name']));
        $stmt->execute();
        echo $db->lastInsertId();
        $db = null;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function updateUser($id) {
    $request = Slim::getInstance()->request();
    $body = $request->getBody();
    $wc = json_decode($body);
    $sql = "UPDATE user SET user_name=:user_name, email=:email, password=:password WHERE id=:id";
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("user_name", $wc->user_name);
        $stmt->bindParam("email", $wc->email);
        $stmt->bindParam("password", $wc->password);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $db = null;
        echo json_encode(utf8ize($wine));
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function getLastReview($id) {
	$sql = "SELECT * FROM review LEFT JOIN wc ON review.wc_id = wc.WC_id where review.user_id = :id UNION SELECT * FROM review RIGHT JOIN wc ON review.wc_id = wc.WC_id where review.user_id = :id";
	try {
		$db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $lastreview = $stmt->fetchAll();
        $db = null;
        echo json_encode(utf8ize($lastreview));
        //print_r( $lastreview);
	} catch(PDOException $e) {
	    //error_log($e->getMessage(), 3, '/var/tmp/php.log');
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}


function getWcInfo($id) {
	$sql = "SELECT WC_id,wc_name, type,latitude,longitude, prix, wc_cnt, IFNULL(sum(v.note) / count(*),'undef') as note
        from
        (
            SELECT wc.WC_id, wc.wc_name, wc.type, wc.latitude, wc.longitude, wc.prix, wc.wc_cnt, review.note
            FROM wc
            LEFT JOIN review ON wc.WC_id = review.WC_id
            WHERE wc.WC_id =".$id." UNION 
            SELECT wc.WC_id, wc.wc_name, wc.type, wc.latitude, wc.longitude, wc.prix, wc.wc_cnt, review.note
            FROM wc
            RIGHT JOIN review ON wc.WC_id = review.WC_id
            WHERE wc.WC_id =".$id.") v
            group by 
            WC_id,wc_name, type,latitude,longitude, prix, wc_cnt";
	try {
        $db = getDB();
        $stmt = $db->query($sql);
        $wc = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        echo '{"wc": ' . json_encode(utf8ize($wc)) . '}';
	} catch(PDOException $e) {
	    //error_log($e->getMessage(), 3, '/var/tmp/php.log');
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function getWcReview($id) {
    $sql = "SELECT review.review_id, review.comment
            FROM wc
            LEFT JOIN review ON wc.WC_id = review.WC_id
            WHERE wc.WC_id = ".$id." and review.comment != ''
            UNION 
            SELECT review.review_id, review.comment
            FROM wc
            RIGHT JOIN review ON wc.WC_id = review.WC_id
            WHERE wc.WC_id = ".$id." and review.comment != ''";
    try {
        $db = getDB();
        $stmt = $db->query($sql);
        $wc = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        echo '{"wc": ' . json_encode(utf8ize($wc)) . '}';
    } catch(PDOException $e) {
        //error_log($e->getMessage(), 3, '/var/tmp/php.log');
        echo '{"error":{"text":'. $e->getMessage() .'}}'; 
    }
}

function getWcNote($id) {
    $sql = "SELECT v.WC_id, sum(v.note) / count(*) as noteGlobal FROM (select wc.WC_id, review.note from wc, review where wc.WC_id=:id) v group by v.WC_id";
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $wcinfo = $stmt->fetchObject();
        $db = null;
        echo json_encode(utf8ize($wcinfo));
    } catch(PDOException $e) {
        //error_log($e->getMessage(), 3, '/var/tmp/php.log');
        echo '{"error":{"text":'. $e->getMessage() .'}}'; 
    }
}

function getGPlaces($latitude, $longitude) {
    try {
        //ATTENTION : Les paramètres d'entrées longitude et latitude doivent être des float avec unr virgule et non un point comme séparateur entre les unités et les décimales sinon l'url interprète le point
        // Par contre pour l'api google places le séparateur doit être un point, on va donc faire un replace de la latitude et de la longitude
        $latitude = str_replace(',','.', $latitude );
        $longitude = str_replace(',','.', $longitude );

        // On sélectionne certains type de places ou on part du postulat qu'il y a des toilettes : bar, cafe, restaurant , museum
        // On va d'abord construire l'url qui permet de requeter la base google. NB : le rayon est en mètre. On recherche 500 mètres autour de la position de l'user. 
        $google_place_url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location='.$latitude.','.$longitude.'&radius=500&types=cafe|restaurant|bar|museum&key=AIzaSyAhdUvlad0wbGnqm8RMm7ivVuZeUC7DqWM';

        // On fait ensuite la requête http en get avec curl
        $gplaces_json = get_data_with_curl($google_place_url);
        // On décode le json pour pouvoir le traiter
        $gplaces_array = json_decode($gplaces_json);

        // On teste si il y a un résultat
        if($gplaces_array->status == 'ZERO_RESULTS'){
            echo json_encode("No result");
            return;
        }

        // On va récupérer les eléments qui nous intéressent et les remettre dans un tableau à nous que l'on renverra sous la forme de json
        $gplaces_result = $gplaces_array->results;

        // On déclare un tableau contenant nos résultes
        $array_without_token = null;
        // On boucle sur la liste des résultats
        for($i = 0; $i < count($gplaces_result); $i++){
        //for($i = 0; $i < 1; $i++){
            // On veut récupérer la longitude et la latitude
            $array_without_token[$i]['latitude'] = $gplaces_result[$i]->geometry->location->lat;
            $array_without_token[$i]['longitude'] = $gplaces_result[$i]->geometry->location->lng;
            // Le nom de l'endroit
            $array_without_token[$i]['name'] = $gplaces_result[$i]->name;
            // L'adresse de l'endroit
            $array_without_token[$i]['adress'] = $gplaces_result[$i]->vicinity;
            // le type d'endroit
            $array_without_token[$i]['type'] = $gplaces_result[$i]->types[0];
        }

        // La requête vers l'api google places renvoie au maximum 60 résultats. Ces résultats sont séparé en 3 json de 20. 
        // Le résultat nous donne un token à mettre en paramètre pour avoir les 20 résultats suivants. Néanmoins il faut 2 secondes entre les 2 appels (limite imposé par google). On va donc renvoyer dans notre json a nous le token pour réapeler l'api dans le web (comme ca on peut commencer le chargement des 20 premiers résultats sur la carte)

        // On décalare le tableau final
        $final_array = null;
        // On test si il y a un next token
        if(isset($gplaces_array->next_page_token)){
            $final_array['result'] = $array_without_token;
            $final_array['pagetoken'] = $gplaces_array->next_page_token;
        }
        else{
            // Si non on ne l'ajoute pas
            $final_array['result'] = $array_without_token;
            $final_array['pagetoken'] = "null";
        }

        // On renvoit le json nouvellement crée pour qu'il soit interprété par le web
        echo json_encode($final_array);

    } catch(PDOException $e) {
        //error_log($e->getMessage(), 3, '/var/tmp/php.log');
        echo '{"error":{"text":'. $e->getMessage() .'}}'; 
    }
}

function getGPlacesToken($latitude, $longitude, $next_page_token) {
    try {
        //ATTENTION : Les paramètres d'entrées longitude et latitude doivent être des float avec unr virgule et non un point comme séparateur entre les unités et les décimales sinon l'url interprète le point
        // Par contre pour l'api google places le séparateur doit être un point, on va donc faire un replace de la latitude et de la longitude
        $latitude = str_replace(',','.', $latitude );
        $longitude = str_replace(',','.', $longitude );

        // On sélectionne certains type de places ou on part du postulat qu'il y a des toilettes : bar, cafe, restaurant , museum
        // On va d'abord construire l'url qui permet de requeter la base google. NB : le rayon est en mètre. On recherche 500 mètres autour de la position de l'user. Dans cette fonction on rajoute le next page token pour avoir la seconde ligne de résultat
        $google_place_url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location='.$latitude.','.$longitude.'&radius=500&types=cafe|restaurant|bar|museum&key=AIzaSyAhdUvlad0wbGnqm8RMm7ivVuZeUC7DqWM&pagetoken='.$next_page_token;

        // On fait ensuite la requête http en get avec curl
        $gplaces_json = get_data_with_curl($google_place_url);
        // On décode le json pour pouvoir le traiter
        $gplaces_array = json_decode($gplaces_json);

        // On teste si il y a un résultat
        if($gplaces_array->status == 'ZERO_RESULTS'){
            echo json_encode("No result");
            return;
        }

        // On va récupérer les eléments qui nous intéressent et les remettre dans un tableau à nous que l'on renverra sous la forme de json
        $gplaces_result = $gplaces_array->results;

        // On déclare un tableau contenant nos résultes
        $array_without_token = null;
        // On boucle sur la liste des résultats
        for($i = 0; $i < count($gplaces_result); $i++){
        //for($i = 0; $i < 1; $i++){
            // On veut récupérer la longitude et la latitude
            $array_without_token[$i]['latitude'] = $gplaces_result[$i]->geometry->location->lat;
            $array_without_token[$i]['longitude'] = $gplaces_result[$i]->geometry->location->lng;
            // Le nom de l'endroit
            $array_without_token[$i]['name'] = $gplaces_result[$i]->name;
            // L'adresse de l'endroit
            $array_without_token[$i]['adress'] = $gplaces_result[$i]->vicinity;
            // le type d'endroit
            $array_without_token[$i]['type'] = $gplaces_result[$i]->types[0];
        }

        // La requête vers l'api google places renvoie au maximum 60 résultats. Ces résultats sont séparé en 3 json de 20. 
        // Le résultat nous donne un token à mettre en paramètre pour avoir les 20 résultats suivants. Néanmoins il faut 2 secondes entre les 2 appels (limite imposé par google). On va donc renvoyer dans notre json a nous le token pour réapeler l'api dans le web (comme ca on peut commencer le chargement des 20 premiers résultats sur la carte)

        // On décalare le tableau final
        $final_array = null;
        // On test si il y a un next token
        if(isset($gplaces_array->next_page_token)){
            $final_array['result'] = $array_without_token;
            $final_array['pagetoken'] = $gplaces_array->next_page_token;
        }
        else{
            // Si non on ne l'ajoute pas
            $final_array['result'] = $array_without_token;
            $final_array['pagetoken'] = "null";
        }

        // On renvoit le json nouvellement crée pour qu'il soit interprété par le web
        echo json_encode($final_array);

    } catch(PDOException $e) {
        //error_log($e->getMessage(), 3, '/var/tmp/php.log');
        echo '{"error":{"text":'. $e->getMessage() .'}}'; 
    }
}


function get_data_with_curl($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

// fonction pour encoder chaque élément d'un tableau pour qu'il puisse etre convertit en json sans problemes
function utf8ize($d) {
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string ($d)) {
        return utf8_encode($d);
    }
    return $d;
}

?>