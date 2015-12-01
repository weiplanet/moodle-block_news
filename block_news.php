<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/news/block_news_system.php');
require_once($CFG->dirroot . '/blocks/news/block_news_message.php');

/**
 * News block main class.
 *
 * @package block_news
 * @copyright 2012 The Open University
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @author OU developers
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_news extends block_base {

    public $bns='';

    public function init() {
        $this->title = get_string('pluginname', 'block_news');
    }

    /**
     * multiple instance control
     * note: administrator can override
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * return true if the block has settings.php file.
     */
    public function has_config() {
        return true;
    }

    /**
     * Display block content
     */
    public function get_content() {
        global $CFG, $PAGE, $COURSE, $USER, $SESSION;

        if ($this->content !== null) {
            return $this->content;
        }

        $blockinstanceid=$this->instance->id;
        $blockcontext = context_block::instance($blockinstanceid);

        $this->content = new stdClass;

        $output = $PAGE->get_renderer('block_news'); //looks for class xxx_renderer
        $this->content->footer='';

        // show Add if permittted
        if (has_capability('block/news:add', $blockcontext)) {
            $this->content->footer .= $output->container_start(null, 'block_news_addmsg');
            $this->content->footer .= $output->render_add($blockinstanceid);
            $this->content->footer .= $output->container_end();
            $canaddnews = 'blocknewscanadd';// Extra class added on some links if edit permission.
        } else {
            $canaddnews = null;
        }
        $nummsgs=$this->bns->get_nummessages();
        $msgs=$this->bns->get_messages_limited($nummsgs);

        $sumlen=$this->bns->get_summarylength();
        if ($msgs) {
            $c=1;
            $this->content->text = $output->open_news_block_custom_wrapper();
            $this->content->text .= $output->container_start('block_news_msglist');

            $newmsg = false;

            foreach ($msgs as $msg) {
                //check whether there are news posts the user is not likely to have seen
                if (!empty($SESSION->news_block_views) &&
                        !empty($SESSION->news_block_views[$msg->get_id()])) {
                    $courseaccess = time();
                } else if (isset($USER->lastcourseaccess[$COURSE->id])) {
                    $courseaccess = $USER->lastcourseaccess[$COURSE->id];
                } else {
                    $courseaccess = false;
                }

                $msgdate = $msg->get_messagedate();
                $msguserid = $msg->get_userid();

                if ($USER->id != $msguserid && (!$courseaccess || $msgdate > $courseaccess)) {
                    $newmsg = true;
                }

                $msgwidget = new block_news_message_short($msg, $this->bns, $sumlen, $c);
                $this->content->text .= $output->render($msgwidget);
                $c++;
            }

            if ($newmsg) {
                $this->title = $output->render_block_news_new_messages($this->title);
            }

            $this->content->text .= $output->container_end();
            $this->content->text .= $output->close_news_block_custom_wrapper();
            // main footer
            $this->content->footer .= $output->container_start($canaddnews, 'block_news_viewall');
            $this->content->footer .= $output->render_view_all($blockinstanceid);
            $this->content->footer .= $output->container_end();
        } else {
            $this->content->text = $output->container(
                    get_string('msgblocknonews', 'block_news'), null, 'msgblocknonews');
            if (has_capability('block/news:viewhidden', $blockcontext)) {
                $this->content->footer .= $output->container_start(null, 'block_news_viewall');
                $this->content->footer .= $output->render_view_all($blockinstanceid);
                $this->content->footer .= $output->container_end();
            }
        }

        // if feeds allowed on site, display icon
        if (isset($CFG->enablerssfeeds) && $CFG->enablerssfeeds) {
            $this->content->footer .= $output->container_start($canaddnews, 'block_news_rss');
            $pi = new pix_icon('i/rss', 'RSS');
            $this->content->footer .= $output->action_icon(
                $this->bns->get_feed_url(), $pi, null, array('title'=>'RSS'));
            $this->content->footer .= $output->container_end();
        }

        return $this->content;
    }

    /**
     * instance delete
     */
    public function instance_delete() {
        $this->bns->delete();
    }

    /**
     * instance settings
     */
    public function specialization() {
        $blockinstanceid=$this->instance->id;
        $this->bns=block_news_system::get_block_settings($blockinstanceid);

        // display title of this instance on config page (its put in block header and on
        // block edit page header)
        $t=$this->bns->get_title();
        if (!empty($t)) {
            $this->title =$t;
        }
    }

    /*
     *  store config data for instance
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB;

        $this->bns->save($data);

        // force some config data (necessary for backup/restore)
        $config = new stdClass;
        $config->title = $data->title;
        $config->id = $this->instance->id;
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)),
                        array('id' => $this->instance->id));
    }

    public function instance_create() {
        // force some initial data for the block
        $data = new stdClass();
        $data->title = get_string('defaultblocktitle', 'block_news');
        $data->feedurls = '';

        $this->instance_config_save($data);

        return true;
    }

    /**
     *  limit contexts from which block can be created
     */
    public function applicable_formats() {
        return array('course' => true, 'mod' => true);

    }
}
