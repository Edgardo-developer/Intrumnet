<?php
/**
 * 
 * Class: Intrumnet_addProduct
 * Description: Import Products from intrumnet CRM
 * 
 */
class Intrumnet_addProduct{
    private $cats = array( 1   => 'apartment', 2   => 'commercial', 3   => 'house-area	', 4   => 'parking', 5   => 'foreigh'); 
    public $cat;
    public $id;
    public $fields;
    public $name;
    public $content;
    public $uniteDiff;
    public $objectSingle;

    public function __construct($objectSingle, $metakeys, $agentids){
        $this->cat              = get_category_by_slug(self::$cats[$objectSingle->stock_type]);
        $this->name             = $objectSingle->name == "" ? $objectSingle->id : $objectSingle->name;
        $this->fields           = $objectSingle->fields;
        $this->metakeys         = $metakeys;
        $this->uniteDiff        = array();
        $this->id               = $objectSingle->id;
        $this->author           = $objectSingle->author;
        $this->agents           = $agentids;

        foreach ($this->fields as $FieldValue) {
            $listIds = array(624, 625, 626);
            if (in_array($FieldValue->id, $listIds)) {
                $this->content = $FieldValue->value;
            }
        }
    }

    /**
     * 
     * Function: uniteDiff
     * Description: search the diff between ACF fields and intrumnet fields
     * 
     */
    private function uniteDiff(){
        $united = array(array(), array());
        $diff = array(array(), array());
        $photos_array = array(array(),array());
        $uniteDiff = array();

        foreach ($this->fields as $field_single) {

            foreach ($this->metakeys as $metakey) {
                if ($metakey == $field_single->slug) {
                    $united[0][] = $field_single->slug;
                    $united[1][] = $field_single->value;
                }else if( $metakey != $field_single->slug && (in_array($field_single->slug, $united[0]) || in_array($field_single->slug, $diff[0]) || in_array($field_single->slug, $photos_array[0]))){
                    continue 1;
                }else if($metakey != $field_single->slug){
                    $photos = strpos($field_single->slug, 'photos');
                    if($photos === false){
                        if (!is_numeric(strpos($field_single->slug, 'adtInfo')) && !is_numeric(strpos($field_single->slug, 'mainInfo')) ) {
                            $diff[0][] = $field_single->slug;
                            $diff[1][] = $field_single->name;
                            $diff[2][] = $field_single->value;
                        }
                    }else{
                        $photos_array[0][] = $field_single->slug;
                        $photos_array[1][] = 'https://{DOMAIN}.intrumnet.com/files/crm/product/'.$field_single->value;
                    }
                }

            }
        }
        $uniteDiff[0] = $united;
        $uniteDiff[1] = $diff;
        $uniteDiff[2] = $photos_array;
        return $uniteDiff;
    }

    /**
     * 
     * Function: createObject
     * Description: create new post
     * 
     */
    public function createObject(){
        $post_data = array(
            'post_title'    => $this->name,
            'post_content'  => $this->content ? $this->content : " ",
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_category' => array($this->cat->cat_ID),
        );
        $post_id = wp_insert_post( $post_data );
        $this->addMeta($post_id);
        $this->addImages($post_id);
        return $post_id;
    }

    /**
     * 
     * Function: addACF
     * Description: update ACF field
     * 
     */
    private function addACF($array, $post_id){

        $acfFields_array = array(
            'adtInfo'   => array(),
            'generalInfo'   => array(),
            'mainInfo'      => array(),
            'mainid'        => $this->id,
        );

        foreach ($array[0] as $key => $value) {
            if (is_numeric(strpos($value, 'adtInfo'))) {
                $prefix = 'adtInfo';
            }else if(is_numeric(strpos($value, 'generalInfo'))){
                $prefix = 'generalInfo';
            }else if(is_numeric(strpos($value, 'mainInfo'))){
                $prefix = 'mainInfo';
            }
            $acfFields_array[$prefix][$value] = $array[1][$key];
        }

        foreach ($this->agents as $key => $value) {
            if ($value[0] == $this->author) {
                foreach ($this->agents as $agent) {
                    if ($agent[0] == $this->author) {
                        update_field('mainInfo_mainInfo_agent', array("0"=>$agent[1]), $post_id);
                    }
                }
                break;
            }
        }

        foreach ($acfFields_array as $key => $value) {
            update_field($key, $value, $post_id);
        }
    }

    /**
     * 
     * Function: addExtra
     * Description: add meta field
     * 
     */
    private function addExtra($array, $post_id){
        add_metadata( 'post', $post_id, "custom", array($array[1], $array[2]));
    }

    /**
     * 
     * Function: addMeta
     * Description: add meta field
     * 
     */
    private function addMeta($post_id){
        $post_meta = $this->uniteDiff();
        if (count($post_meta[0][0]) > 0) {
            $this->addACF($post_meta[0], $post_id);
        }
        if (count($post_meta[1][0]) > 0) {
            $this->addExtra($post_meta[1], $post_id);
        }
    }

    /**
     * 
     * Function: addImages
     * Description: add image to post
     * 
     */
    private function addImages($post_id){
        $images = $this->uniteDiff()[2];
        $ids = array();
        foreach ($images[1] as $key => $url) {
            $tmp = download_url( $url );

            $file_array = [
                'name'     => basename( $url ),
                'tmp_name' => $tmp
            ];
            if ($key == 0) {
                $id = media_handle_sideload( $file_array, $post_id );
                set_post_thumbnail( $post_id, $id );  
            }else if ($key == count($images[1]) - 1) {
                $ids[] = media_handle_sideload( $file_array, $post_id );  
                update_field('photos', implode(",", $ids), $post_id);
            }else{
                $ids[] = media_handle_sideload( $file_array, $post_id );  
            }
            
            @unlink( $tmp );
        }

    }
}
