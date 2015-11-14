<?php

//=== Istiak PHP_Search ===
//Contributors: Istiak Mah
//Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2J4GWJE5SKQE

namespace AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AdminBundle\Library\Menu;

class ContentController extends Controller {

    protected $menu;

    public function __construct() {
        $this->menu = new Menu('home');
    }

    /**
     * @param Request $request
     * @return Response
     */
    function contentAction(Request $request) {

        $tic = microtime(true);
        $request = $this->getRequest();


        if (($handle = fopen("/Users/istiak/Documents/OneDrive/GIT/php_search_copy/src/AdminBundle/feed.csv", "r")) !== FALSE) {
            $all_data = array();
            $products = array();

            $i = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                $doc = array();
                $doc['id'] = $data[0];
                $doc['category'] = $data[1];
                $doc['country'] = $data[2];
                $doc['sale_price'] = $data[3];
                $doc['price'] = $data[4];
                $doc['currency'] = $data[5];
                $doc['vat'] = $data[6];
                $doc['product_name'] = $data[7];
                $doc['description'] = $data[8];
                $doc['image_url'] = $data[9];
                $doc['sku'] = $data[10];
                $doc['stock'] = $data[11];
                $doc['variation_name'] = $data[12];
                $doc['propertyName'] = $data[13];
                $doc['propertyValue'] = $data[14];

                $all_data[] = $doc;
            }
            fclose($handle);
        }


        array_walk_recursive($all_data, function (&$item) {
            $item = strtolower($item);
        });
        //echo "<pre>";print_r($all_data);echo "</pre>";die;
        $groupBy = array();
        $labels = array();

        // Group all documents based on the category
        foreach ($all_data as $data) {
            array_push($labels, $data['category']);
            $id = $data['category'];
            if (isset($groupBy[$id])) {
                $groupBy[$id][] = $data;
            } else {
                $groupBy[$id] = array($data);
            }
        }

        // convert all the data into small letter so can be findable
        array_walk_recursive($groupBy, function (&$item) {
            $item = strtolower($item);
        });
        $groupBy = array_change_key_case($groupBy, CASE_LOWER);
        //echo "<pre>";print_r($groupBy);echo "</pre>";die;
        // all unique catagory's which can be shown on the page as well
        // if a user choose one category, the user will get the the products which belongs to that category
        $categoryUnq = array_unique($labels);
        $categoryUnq = array_map('strtolower', $categoryUnq);

        if ($request->getMethod() == 'POST') {

            $content = $request->request->get('contentSearch');

            // Step : 1 --> make all the request small latter since PHP is case sensative
            $contentSearch = (utf8_encode(strtolower($content)));
            // for some special words like ä å ö ...
            $contentSearch = htmlentities($contentSearch);

            // Step : 2 --> handle the negetive word, if there is any negetive number in the request process
            if (preg_match('/\-[\d]+/', $contentSearch)) {

                // Step : 3 --> handle the negetive word and reduce a value from input, like -2 become 1    
                $contentSearch = preg_replace_callback('/-\d+/', function($matches) {
                    return abs($matches[0]) - 1;
                }, $contentSearch);
            } else {
                // we do not need anything in the else part because if there is no negetive number it will take the default request
            }

            // Step : 4 --> If the request data is a Category return all belongins data
            if (in_array($contentSearch, $categoryUnq)) {
                if (array_filter($categoryUnq, function($b) use ($contentSearch) {
                            return stripos($b, $contentSearch) !== false;
                        })) {
                    $elements = $groupBy[($contentSearch)];

                $products = array();
                $i = 0;
                $key_array = array();

                if (!empty($elements)) {
                    // loop for sorting the grouped values
                    foreach ($elements as $val) {
                        if (!in_array($val, $key_array)) {
                            $key_array[$i] = $val;
                            $products[$i] = $val;
                        }
                        $i++;
                    }
                        }}
            } else {
                // Step : 5 --> If the request does not belongs to any Category 
                // it will try to search from all the documents
                $search = $this->my_array_search($all_data, $contentSearch);

                $products = array();
                $i = 0;
                $key_array = array();

                if (!empty($search)) {
                    // loop for sorting the grouped values
                    foreach ($search as $val) {
                        if (!in_array($val, $key_array)) {
                            $key_array[$i] = $val;
                            $products[$i] = $val;
                        }
                        $i++;
                    }
                } elseif (empty($search)) {

                    // Step : 6 --> If the request does not match any documents there is a probability
                    // that the user is request it in a reverser way, so we also reverse the request to make it even

                    $temp = explode(" ", $contentSearch);
                    $contentR = array();
                    for ($i = count($temp) - 1; $i >= 0; $i--) {
                        $contentR [] = $temp[$i] . " ";
                    }
                    // reverse request value
                    $contentStr = implode(" ", $contentR);

                    $searchAgain = $this->my_array_search($all_data, $contentStr);

                    $products = array();
                    $i = 0;
                    $key_array = array();

                    if (!empty($searchAgain)) {
                        // loop for sorting the grouped values
                        foreach ($searchAgain as $val) {
                            if (!in_array($val, $key_array)) {
                                $key_array[$i] = $val;
                                $products[$i] = $val;
                            }
                            $i++;
                        }
                    }
                    // Step : 7 --> If Nothing have been found than the return message
                } else {
                    
                }
            }
        }

//        $labelsUnq = array();
        $labelsUnq[] = array_values($all_data[0]);
        //echo "<pre>";print_r($labelsUnq);echo "</pre>";die;

        $time = (" !!! Took: " . round((microtime(true) - $tic)) . "s");

        // Content view page
        return $this->render('AdminBundle:Content:content.html.twig', array(
                    'pageTitle' => 'Istiak Contents',
                    'menu' => $this->menu,
                    'products' => $products,
                    'labelsUnq' => $labelsUnq,
                        )
        );
    }

    function my_array_search($array, $string) {
        $ret = false;
        $pattern = preg_replace('/\s+/', ' ', preg_quote($string, '/'));
        foreach ($array AS $k => $v) {
            $res = preg_grep('/' . $pattern . '/', $v);
            if (!empty($res))
                $ret[$k] = $v;
        }

        return $ret;
    }

}
