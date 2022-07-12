<?php
/**
 * 
 * Обеспечивает связь по API и создает агентов
 * 
 */
class Intrumnet_addAgent
{
    public $url;
    public $args;
    public $method;
    public function __construct($url, $args = 0, $trip = 1, $method){
        $this->post = array('apikey' =>"APIKEY",);
        $this->url = $url;
        $this->method = $method;
        if ($method == 'products' || $method == 'managers') {
           $this->post['params'] = $args;
        }
        $this->trip = $trip;
    }

    /**
     * 
     * Function: getData
     * Description: connect to API intrumnet
     * 
     */
    private function getData(){
        $i_start = 1;
        $i_finish = $this->trip;
        $results = array();
        while ($i_start <= $i_finish) {

            if ($this->method == 'products') {
                $this->post['params']['type'] = $i_start; 
            } 

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url);  
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->post));  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  

            if ($this->method == 'fields' || $this->method == 'managers') {
                $results = json_decode(curl_exec($ch), true)['data'];
            }else{
                $results[] = json_decode(curl_exec($ch));
            }
            curl_close($ch);
            $i_start++;
        }
        return $results;
    }
    
    /**
     * 
     * Function: getFields
     * Description: get only fields
     * 
     */
    public function getFields(){
        return $this->getData();
    }

    /**
     * 
     * Function: getObjects
     * Description: get only objects
     * 
     */
    public function getObjects(){
        return $this->getData();
    }

    /**
     * 
     * Function: getAgents
     * Description: get agents
     * 
     */
    public function getAgents(){
        $agents_object = $this->getData();
        $agent_sum = array();
        foreach($agents_object as $agent){
            $data_title = "";
            foreach ($agent as $key => $value) {
                switch ($key) {
                    case "id":              
                        $agent_sum[$key][] = $value;
                        break 1;
                    case "name":            
                        $data_title .= " " . $value;                                                                
                        break 1;
                    case "surname":         
                        $data_title .= " " . $value;                                                                
                        break 1;
                    case "secondname":      
                        $agent_sum['title'][] = $data_title . " " . $value;                                     
                        break 1;
                    case "mobilephone":     
                        $agent_sum[$key][] = $value[0]['phone'];                                                    
                        break 1;
                    case "avatars":         
                        $a = explode('?', $value['original']); 
                        $agent_sum[$key][] = "https://silvercity.intrumnet.com/".$a[0];                
                        break 1;
                }
            }
            unset($data_title);
        }
        $this->createAgent($agent_sum);
        return $agent_sum;
    }

    /**
     * 
     * Function: createAgent
     * Description: create new agent as "agent" post
     * 
     */
    private function createAgent($agents){
        $agent_size = count($agents['id']);
        $template = array(
            'post_type'     => "agent",
            'post_title'    => "",
            'post_content'  => "",
            'post_status'   => 'publish',
            'post_author'   => 1,
        );
        $i = 0;
        while($i < $agent_size){
            $template['post_title'] = $agents['title'][$i];
            $post_id = wp_insert_post( $template );
            $this->addMeta($post_id, $agents['mobilephone'][$i], $agents['id'][$i]);

            if ($agents['avatars'] !== "" && $agents['avatars'] !== NULL) {
                $this->addImages($post_id, $agents['avatars'][$i]);
            }

            ++$i;
        }
    }

    /**
     * 
     * Function: addImages
     * Description: import thumbnail
     * 
     */
    private function addImages($post_id, $image_url){
            $tmp = download_url( $image_url );

            $file_array = [
                'name'     => basename( $image_url ),
                'tmp_name' => $tmp
            ];

            $id = media_handle_sideload( $file_array, $post_id );
            set_post_thumbnail( $post_id, $id );  
            
            @unlink( $tmp );
    }

    /**
     * 
     * Function: addMeta
     * Description: update fields of agent
     * 
     */
    private function addMeta($post_id, $number, $id){
        update_field("agent_number", $number, $post_id);
        update_field("agentid", $id, $post_id);
    }
}
