<?php
/**
 * Hubspot API
 */
class hubspot_api
{
    public $client_id;
    public $access_token;

    public function __construct()
    {
        $this->client_id = '3765607d-c88e-11e5-a2bf-bd028721085c';
    }

    public function get_auth_url($hubid)
    {
        $auth_url = add_query_arg(array(
            'client_id'    => $this->client_id,
            'portalId'     => $hubid,
            'redirect_uri' => plugin_dir_url(__FILE__),
            'scope'        => 'offline',
        ), 'http://https://app.hubspot.com/auth/authenticate');
        return $auth_url;
    }

    public function refresh_token($refresh_token = "", $registered_time = "", $expires_in = "")
    {
        if (!($refresh_token == "")) {
            $expires_in = '+ ' . $expires_in . ' second';
            $expires_at = new DateTime($registered_time);
            $expires_at = $expires_at->modify($expires_in);
            $now        = new DateTime();
            if ($now > $expires_at) {
                $url = add_query_arg(array(
                    'client_id'     => $this->client_id,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ), 'https://api.hubapi.com/auth/v1/refresh');

                $response = wp_remote_post($url, array(
                    'method'      => 'POST',
                    'timeout'     => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    // 'headers' => array(),
                )
                );

                $url = add_query_arg(array(
                    'client_id'     => $this->client_id,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ), 'https://api.hubapi.com/auth/v1/refresh');

                $response = wp_remote_post($url, array(
                    'method'      => 'POST',
                    'timeout'     => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    // 'headers' => array(),
                )
                );

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    return false;
                } else {
                    $new_token = json_decode($response['body']);
                    return $new_token;
                }

            }
        }
    }

    public function get_lists()
    {
        $all_lists = array();
        $has_more  = false;
        $offset    = 0;
        $count     = 20;

        do {
            $url = add_query_arg(array(
                'access_token' => $this->access_token,
                'count'        => $count,
                'offset'       => $offset,
            ), 'https://api.hubapi.com/contacts/v1/lists');

            $response = $response = wp_remote_get($url);
            if (is_array($response)) {
                $response_body = $response['body']; // use the content

                if (wp_remote_retrieve_response_code($response) == 200) {
                    $response_arr = json_decode($response_body);
                    $has_more = $response_arr->{'has-more'};
                    foreach ($response_arr->lists as $list) {
                        if (!$list->dynamic) {
                            $all_lists[$list->listId] = $list->name;
                        }
                    }
                    if ($has_more) {
                        $offset++;
                    } else {
                        $has_more = false;
                    }
                }
            }
        } while ($has_more == true);

        return $all_lists;
    }


    public function add_property($property,$portalId)
    {
        $url = add_query_arg(array(
            'access_token' => $this->access_token,
            'portalId' =>$portalId,
        ), ' https://api.hubapi.com/contacts/v2/properties');

        $response = wp_remote_request($url, 
            array(
            'method'  => 'PUT',
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => $property,
            ));
    }

    public function create_contact($contact = "")
    {
        $contact_id='';
        $url = add_query_arg(array(
            'access_token' => $this->access_token,
        ), 'https://api.hubapi.com/contacts/v1/contact');

        $response = wp_remote_post($url, array(
            'method'   => 'POST',
            'blocking' => true,
            // 'headers' => array('Content-Type' =>'application/json'),
            'body'     => $contact,
            'cookies'  => array(),
        )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            self::log( sprintf('Something went wrong: %s',$error_message ));
        } else {
            $contact_id="";
            if (is_array($response)) {
                $response_body = $response['body']; // use the content
                if (wp_remote_retrieve_response_code($response) == 200) {
                    $response_arr = json_decode($response_body);
                    $contact_id=$response_arr->vid;
                }
            }
        }
        return $contact_id;
    }

    public function add_contact_to_list($listId, $contact_id)
    {
        $contact         = array();
        $contact['vids'] = array($contact_id);
        $contact         = json_encode($contact);

        $url = add_query_arg(array(
            'access_token' => $this->access_token,
        ), 'https://api.hubapi.com/contacts/v1/lists/' . $listId . '/add');
        

        $response = wp_remote_post($url, array(
            'method'   => 'POST',
            'headers'  => array('Content-Type' => 'application/json'),
            'blocking' => true,
            'body'     => $contact,
            // 'cookies' => array()
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            // return false;
        } else {
            $response_body = json_decode($response['body']);
            // return $response_body->vid;
        }
        
    }
    static function log( $message ) {
        // if ( WP_DEBUG === true ) {
            $logger = new WC_Logger();

            if ( is_array( $message ) || is_object( $message ) ) {
                $logger->add( 'hubspot_woo_integration', print_r( $message, true ) );
            }
            else {
                $logger->add( 'hubspot_woo_integration', $message );
            }
    }
}
