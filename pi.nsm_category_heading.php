<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Plugin Info
 */
$plugin_info = array(
    'pi_name'        => 'NSM Category Heading',
    'pi_version'     => '0.0.1',
    'pi_author'      => 'Leevi Graham',
    'pi_author_url'  => 'http://newism.com.au',
    'pi_description' => 'A replacement for the native Channel Category Heading Tag',
    'pi_usage'       => Nsm_category_heading::usage()
);

class Nsm_category_heading
{
    /**
     * @var string
     */
    public $return_data = '';

    /**
     * {exp:nsm_category_heading}
     */
    public function __construct()
    {
        $ee = ee();
        $category = null;
        $categories = array();

        $categoryUrlTitle = $ee->TMPL->fetch_param('cat_url_title');
        $categoryId = $ee->TMPL->fetch_param('cat_id');
        $channelName = $ee->TMPL->fetch_param('channel');
        $categoryNamePathDelimiter = $ee->TMPL->fetch_param('cat_name_path_delimiter', " - ");

        // -------------------------------------------
        // 'channel_module_category_heading_start' hook.
        //  - Rewrite the displaying of category headings, if you dare!
        //
        if ($ee->extensions->active_hook('channel_module_category_heading_start') === true) {
            $ee->TMPL->tagdata = ee()->extensions->call('channel_module_category_heading_start');
            if ($ee->extensions->end_script === true) {
                return $ee->TMPL->tagdata;
            }
        }

        /** @var CI_DB_active_record $db */
        $db = $ee->db;

        $db->select('
                c.cat_id as category_id,
                c.group_id as category_group_id,
                c.parent_id as category_parent_id,
                c.cat_name as category_name,
                c.cat_url_title as category_url_title,
                c.cat_image as category_image,
                c.cat_description as category_description,
                c.cat_order as category_order,
                ch.channel_id as channel_id,
                ch.channel_title as channel_title,
                ch.channel_name as channel_name
            ');
        $db->from('categories as c');
        $db->join('category_groups as cg', 'c.group_id = cg.group_id');
        $db->join('channels as ch', 'cg.group_id = ch.cat_group');
        $db->where('ch.channel_name', $channelName);

        $query = $db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $categories[$row['category_id']] = $row;
                if ($row['category_url_title'] === $categoryUrlTitle || $row['category_id'] === $categoryId) {
                    $category = $row;
                }
            }
        }

        if (null === $category) {
            $this->return_data = $ee->TMPL->no_results();

            return $this->return_data;
        }


        $categoryNamePathParts = $this->createCategoryNamePathParts($category, $categories);
        $category['category_name_path'] = implode($categoryNamePathParts, $categoryNamePathDelimiter);
        $category['category_name_path_reversed'] = implode(
            array_reverse($categoryNamePathParts),
            $categoryNamePathDelimiter
        );

        $ee->load->library('file_field');
        $categoryImage = $ee->file_field->parse_field($category['category_image']);

        $tagData = $ee->TMPL->tagdata;

        if (true === array_key_exists("category_image", $ee->TMPL->var_pair)) {
            $ee->load->library('api');
            $ee->api->instantiate('channel_fields');
            $ee->api_channel_fields->fetch_installed_fieldtypes();

            $categoryTagPairData = $ee->api_channel_fields->get_pair_field($tagData, 'category_image');
            foreach ($categoryTagPairData as $data) {
                $ee->api_channel_fields->setup_handler('file');
                list($modifier, $content, $params, $chunk) = $data;
                $tpl_chunk = $ee->api_channel_fields->apply(
                    'replace_tag',
                    array(
                        $categoryImage,
                        $params,
                        $content
                    )
                );
                $tagData = str_replace($chunk, $tpl_chunk, $tagData);
            }
        } else {
            $category['category_image'] = $categoryImage['url'];
        }

        $tagData = $ee->TMPL->parse_variables_row($tagData, $category);

        $this->return_data = $tagData;
    }

    /**
     * @param $category
     * @param $categories
     * @param array $titlePathParts
     *
     * @return array
     */
    private function createCategoryNamePathParts($category, $categories, $titlePathParts = array())
    {
        $titlePathParts[] = $category['category_name'];
        if ("" !== $category['category_parent_id'] && true === isset($categories[$category['category_parent_id']])) {
            $parentCategory = $categories[$category['category_parent_id']];
            $titlePathParts = $this->createCategoryNamePathParts($parentCategory, $categories, $titlePathParts);
        }

        return $titlePathParts;
    }

    /**
     * Usage Link
     *
     * @return string
     */
    public static function usage()
    {
        return "http://github.com/newism/nsm.category_heading.ee_addon";
    }
}
