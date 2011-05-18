<?php
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

class assignment_reflection extends assignment_base {

    function assignment_reflection($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'reflection';
    }

    function view() {
        global $CFG, $USER;

        $edit = optional_param('edit', 0, PARAM_BOOL);
        $saved = optional_param('saved', 0, PARAM_BOOL);
        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);

		if((int)get_field('config', 'value', 'name', 'enablegroupings') == 0){
			set_field('config', 'value', '1', 'name', 'enablegroupings');
			add_to_log($this->course->id, "assignment_reflection", "view", "view.php?id={$this->cm->id}", "Assignment id: ".$this->assignment->id." Enable Groupings", $this->cm->id);
			$enablegroupings = true;
		} else {
			$enablegroupings = false;
		}	
		

        require_capability('mod/assignment:view', $context);

        $teacher = has_capability('mod/assignment:grade', $context);
        $submission = $this->get_submission();
        $data2 = false;

        if($submission && is_string($submission->data2)){
			if(strlen($submission->data2) > 1){
				$data2 = unserialize($submission->data2);
			}
		}

        if (!has_capability('mod/assignment:submit', $context)) {
            $editable = null;
        } else {
            $editable = $this->isopen() && (!$submission || $this->assignment->resubmit && !$submission->timemarked);
        }

        $editmode = ($editable and $edit);

        if ($editmode) {
            //guest can not edit or submit assignment
            if (!has_capability('mod/assignment:submit', $context)) {
                print_error('guestnosubmit', 'assignment');
            }
        }

        add_to_log($this->course->id, "assignment_reflection", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);
        // prepare form and process submitted data
        $mform = new mod_assignment_reflection_edit_form();

        $defaults = new object();
        $defaults->id = $this->cm->id;
        if (!empty($submission)) {
            if ($this->usehtmleditor) {
                $options = new object();
                $options->smiley = false;
                $options->filter = false;
                $defaults->text = format_text($submission->data1, $submission->format, $options);
                $defaults->format = FORMAT_HTML;
            } else {
                $defaults->text = clean_text($submission->data1, $submission->format);
                $defaults->format = $submission->format;
            }
        }

        $mform->set_data($defaults);

        if ($mform->is_cancelled()) {
            redirect('view.php?id=' . $this->cm->id, 'cancelled', 0);
        }

        $data = $mform->get_data();
        if ($data) {      // No incoming data?
            if ($editable && $this->update_submission($data)) {
                //TODO fix log actions - needs db upgrade
                $submission = $this->get_submission();
                add_to_log($this->course->id, 'assignment_reflection', 'upload', 'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $this->email_teachers($submission);
                //redirect to get updated submission date and word count
                redirect('view.php?id=' . $this->cm->id . '&saved=1', 'saved', 0);
            } else {
                // TODO: add better error message
                notify(get_string("error")); //submitting not allowed!
            }
        }

        /// print header, etc. and display form if needed
        if ($editmode) {
            $this->view_header(get_string(($submission ? 'editmysubmission' : 'savemysubmission'), 'assignment_reflection'));
        } else {
            $this->view_header();
        }

		// enable groupings message to admin
		if($enablegroupings){
			if(has_capability('moodle/site:config', $context)){
				notify(get_string('enablegroupings', 'assignment_reflection'), 'notifysuccess');
			}
			
		}		
		
        if ($saved) {
            notify(get_string('submissionsaved', 'assignment'), 'notifysuccess');

            $submissionsleft = $this->new_forum();
            if ($submissionsleft == 0){
                notify(get_string('forumcreated', 'assignment_reflection', $this->cm->name.' '.get_string('forum', 'forum')), 'notifysuccess');
            }
            else{
                notify(get_string('forumnotcreated', 'assignment_reflection', $submissionsleft));
            }
            redirect('view.php?id=' . $this->cm->id, '', 0);
        }

        if($teacher){
            if (isset($_POST['mode']) && $_POST['mode'] == 'fastgrade'){
                self::fast_grade();                
            } else {
                echo "<div id=\"mod-assignment-submissions\">";
                self::display_ready_submissions();
                echo "</div>";
            }
        } elseif (has_capability('mod/assignment:submit', $context)) {
            $this->view_intro();

            if ($editmode) {
                print_box_start('generalbox', 'reflection');
                $mform->display();                               
            } else {
                print_box_start('generalbox boxwidthwide boxaligncenter', 'reflection');
                if ($submission) {
                    echo format_text($submission->data1, $submission->format);
                } else if (!has_capability('mod/assignment:submit', $context)) { //fix for #4604
                    echo '<div style="text-align:center">' . get_string('guestnosubmit', 'assignment') . '</div>';
                } else if ($this->isopen()) {    //fix for #4206
                    echo '<div style="text-align:center">' . get_string('emptysubmission', 'assignment') . '</div>';
                }
            }
 
            print_box_end();
            
            if (!$editmode && $editable && (is_object($submission) && $submission->data2 === '0') || !$submission ) {
                echo "<div style='text-align:center'>";
                print_single_button('view.php', array('id' => $this->cm->id, 'edit' => '1'),
                        get_string(($submission ? 'editmysubmission' : 'savemysubmission'), 'assignment_reflection'));
                echo "</div>";
            }

            $dissabled = true; // Ready for grading button status

            if ($data2) {
                $obj = unserialize($submission->data2);
				$forummodule = get_field('modules', 'id', 'name' ,'forum');
                
                if ($data2->status == 1) {
                    $sql = 'SELECT COUNT(id) FROM '.$CFG->prefix.'forum_posts
                            WHERE userid = '.$USER->id.'
                            AND parent != 0
                            AND discussion IN (
                                SELECT id FROM '.$CFG->prefix.'forum_discussions
                                WHERE forum = '.$obj->forumid.')';

                    $count = count_records_sql($sql);

					#$forummodule = get_field('modules', 'id', 'name' ,'forum');						

                    $obj = new stdClass();
                    $obj->count = $this->assignment->var2 -1;

                    $obj->href = '<a href="'.$CFG->wwwroot.'/mod/forum/view.php?id='.get_field('course_modules', 'id','module', $forummodule, 'instance', $data2->forumid).'">';

                    notify(get_string('forumlink', 'assignment_reflection', $obj), 'notifysuccess');

                    if($count >= $obj->count)
                        $dissabled = false; 

                } elseif ($data2->status == 2 && $submission->grade == -1) {	
					$forumlink = '(<a href="'.$CFG->wwwroot.'/mod/forum/view.php?id='.get_field('course_modules', 'id','module', $forummodule, 'instance', $data2->forumid).'">'.get_string('forum', 'forum').'</a>)';
                    notify(get_string('waitingforgrading', 'assignment_reflection').' '.$forumlink, 'notifysuccess');

                } 	elseif ($data2->status == 2 && $submission->grade != -1) {	
						$forumlink = '<a href="'.$CFG->wwwroot.'/mod/forum/view.php?id='.get_field('course_modules', 'id','module', $forummodule, 'instance', $data2->forumid).'">'.get_string('forum', 'forum').'</a>';
	                    notify($forumlink, 'notifysuccess');

	                }

            } elseif($submission && $submission->data2 === '0') {
                $size = get_field("assignment", "var2", "id", $this->cm->instance);
                $current = count_records("assignment_submissions", "assignment", $this->cm->instance, "data2", "0");

                $obj = new stdClass();
                $obj->size = $size;
                $obj->left = $size - $current;

                notify(get_string('waitingforsubmissions', 'assignment_reflection', $obj), 'notifysuccess');
            }

            if(isset($_POST['readyforgrading'])){
                $data2->status = 2;               
                $data2 = serialize($data2);

                set_field("assignment_submissions", "data2", $data2, "id", $_POST['readyforgrading']);
                redirect('view.php?id=' . $this->cm->id, 'ready for grading', 0);
            }               
        }
               
        if(!$teacher){
            if($data2 && $data2->status != 2){
                echo "<div style='text-align:center'>";
                print_single_button('view.php?id=' . $this->cm->id, array('id' => $this->cm->id, 'readyforgrading' => $submission->id),
                    get_string('imreadyforgrading', 'assignment_reflection'), 'post', 'self', false, '', $dissabled);
                echo "</div>";
            }

            $this->view_dates();
            $this->view_feedback();
        }
        $this->view_footer();

    } // End View function


    function fast_grade() {
        global $USER;

        $grading = false;
        $commenting = false;
        $col = false;
        
        if (isset($_POST['submissioncomment'])) {
            $col = 'submissioncomment';
            $commenting = true;
        }
        if (isset($_POST['menu'])) {
            $col = 'menu';
            $grading = true;
        }

        if ($col) {
            foreach ($_POST[$col] as $id => $unusedvalue) {

                $id = (int) $id; //clean parameter name

                $this->process_outcomes($id);

                if (!$submission = $this->get_submission($id)) {
                    $submission = $this->prepare_new_submission($id);
                    $newsubmission = true;
                } else {
                    $newsubmission = false;
                }
                unset($submission->data1);  // Don't need to update this.
                unset($submission->data2);  // Don't need to update this.
                //for fast grade, we need to check if any changes take place
                $updatedb = false;

                if ($grading) {
                    $grade = $_POST['menu'][$id];
                    $updatedb = $updatedb || ($submission->grade != $grade);
                    $submission->grade = $grade;
                } else {
                    if (!$newsubmission) {
                        unset($submission->grade);  // Don't need to update this.
                    }
                }
                if ($commenting) {
                    $commentvalue = trim($_POST['submissioncomment'][$id]);
                    $updatedb = $updatedb || ($submission->submissioncomment != stripslashes($commentvalue));
                    $submission->submissioncomment = $commentvalue;
                } else {
                    unset($submission->submissioncomment);  // Don't need to update this.
                }

                $submission->teacher = $USER->id;
                if ($updatedb) {
					if(!isset($mailinfo))
						$mailinfo = optional_param('mailinfo', null, PARAM_BOOL);
                    $submission->mailed = (int) (!$mailinfo);
                }

                $submission->timemarked = time();

                //if it is not an update, we don't change the last modified time etc.
                //this will also not write into database if no submissioncomment and grade is entered.

                if ($updatedb) {
                    if ($newsubmission) {
                        if (!isset($submission->submissioncomment)) {
                            $submission->submissioncomment = '';
                        }
                        if (!$sid = insert_record('assignment_submissions', $submission)) {
                            return false;
                        }
                        $submission->id = $sid;
                    } else {
                        if (!update_record('assignment_submissions', $submission)) {
                            return false;
                        }
                    }

                    // triger grade event
                    $this->update_grade($submission);

                    //add to log only if updating
                    add_to_log($this->course->id, 'assignment_reflection', 'update grades',
                            'submissions.php?id=' . $this->assignment->id . '&user=' . $submission->userid,
                            $submission->userid, $this->cm->id);
                }
            }
            
            $message = notify(get_string('changessaved'), 'notifysuccess', 'center', true);
            redirect('view.php?id=' . $this->cm->id, $message, 0);
        }
    }

    function new_forum() {
        global $CFG;

        $definedgroupamount = get_field("assignment", "var2", "id", $this->cm->instance);
        $ungroupedsubmissions = count_records("assignment_submissions", "assignment", $this->cm->instance, "data2", "0");

        if ($definedgroupamount <= $ungroupedsubmissions) {

            $timenow = time();

            $group = new object();
            $group->name = get_string('typereflection', 'assignment_reflection').get_string('group','group').date("ymdHis", $timenow);
            $group->courseid = $this->course->id;
            $group->description = "Reflection group";
            $group->id = groups_create_group($group);

            $grouping = new object();
            $grouping->name = get_string('typereflection', 'assignment_reflection').get_string('grouping','group').date("ymdHis", $timenow);
            $grouping->courseid = $this->course->id;
            $grouping->description = "Reflection grouping";
            $grouping->id = groups_create_grouping($grouping);

            groups_assign_grouping($grouping->id, $group->id);

            $forum = new object();
            $forum->course = $this->course->id;
            $forum->name = $this->cm->name.' '.get_string('forum', 'forum').' ';
            $forum->type = 'eachuser';

            $obj = new stdClass();
            $obj->name = $this->cm->name;
            $obj->href = '<a href="'.$CFG->wwwroot.'/mod/assignment/view.php?id='.$this->cm->id.'">';

            $forum->intro = get_string('forumintro', 'assignment_reflection', $obj);

            if (!$forum->instance = forum_add_instance($forum)) {
                return false;
            }

            $forum->section = $this->cm->section;

			#            $forum->module = 5; // forum
			$forummodule = get_field('modules', 'id', 'name' ,'forum');
			$forum->module = $forummodule;


            $forum->coursemodule = add_course_module($forum);
            $forum->section = get_field('course_sections', 'section', 'id', $this->cm->section);

            add_mod_to_section($forum);

            set_coursemodule_visible($forum->coursemodule, 1);
            set_coursemodule_groupmode($forum->coursemodule, 1);
            set_coursemodule_groupingid($forum->coursemodule, $grouping->id);
            set_coursemodule_groupmembersonly($forum->coursemodule, 1);

            $post = new object();
            $post->parent = 0;            
            $post->created = $timenow;
            $post->modified = $timenow;
            $post->mailed = 0;
            $post->attachment = "";
            $post->format = 0; 
            $post->mailnow = 0;
                        
            $discussion = new object();
            $discussion->course = $forum->course;
            $discussion->forum = $forum->instance;
            $discussion->firstpost = 0;
            $discussion->groupid = $group->id;
            $discussion->timemodified = $timenow;
            $discussion->timestart = 0;
            $discussion->timeend = 0;
            
            $asubmids = get_fieldset_select("assignment_submissions", "id", "assignment = {$this->cm->instance} AND data2 = 0 AND LENGTH(data2) = 1");

			if ($asubmids) {
				$i = 0;							
	            foreach ($asubmids as $id) {
	                $s = get_record("assignment_submissions", "id", $id);

	                $subjectname = get_string('typereflection', 'assignment_reflection')." ". ++$i;

	                $discussion->userid = $s->userid;
	                $discussion->usermodified = $s->userid;
	                $discussion->name = $subjectname;
                               
	                $post->discussion = insert_record("forum_discussions", $discussion);
                                
	                $post->userid = $s->userid;
	                $post->subject = $subjectname ;
	                $post->message = $s->data1;
                
	                $post->id = insert_record("forum_posts", $post);

	                $obj = new stdClass();
	                $obj->status = 1;   // Mark is in group
	                $obj->forumid = $discussion->forum;
	                $obj = serialize($obj);
                              
	                set_field("forum_discussions", "firstpost", $post->id, "id", $post->discussion);
	                set_field("assignment_submissions", "data2", $obj, "id", $id); 
	                groups_add_member($group->id, $s->userid);
	            }
			}

            return 0;
        }

        return $definedgroupamount - $ungroupedsubmissions;
    }

    function view_dates() {
        global $USER, $CFG;

        if (!$this->assignment->timeavailable && !$this->assignment->timedue) {
            return;
        }

        print_simple_box_start('center', '', '', 0, 'generalbox', 'dates');
        echo '<table>';
        if ($this->assignment->timeavailable) {
            echo '<tr><td class="c0">' . get_string('availabledate', 'assignment') . ':</td>';
            echo '    <td class="c1">' . userdate($this->assignment->timeavailable) . '</td></tr>';
        }
        if ($this->assignment->timedue) {
            echo '<tr><td class="c0">' . get_string('duedate', 'assignment') . ':</td>';
            echo '    <td class="c1">' . userdate($this->assignment->timedue) . '</td></tr>';
        }
        $submission = $this->get_submission($USER->id);
        if ($submission) {
            echo '<tr><td class="c0">' . get_string('lastedited') . ':</td>';
            echo '    <td class="c1">' . userdate($submission->timemodified);
            /// Decide what to count
            if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_WORDS) {
                echo ' (' . get_string('numwords', '', count_words(format_text($submission->data1, $submission->format))) . ')</td></tr>';
            } else if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_LETTERS) {
                echo ' (' . get_string('numletters', '', count_letters(format_text($submission->data1, $submission->format))) . ')</td></tr>';
            }
        }
        echo '</table>';
        print_simple_box_end();
    }


    function update_submission($data) {
        global $CFG, $USER;

        $submission = $this->get_submission($USER->id, true);

        $update = new object();
        $update->id = $submission->id;
        $update->data1 = $data->text;
        if(!$submission->data2) // Don't change flag if it is set.
            $update->data2 = 0;
        $update->format = $data->format;
        $update->timemodified = time();

        if (!update_record('assignment_submissions', $update)) {
            return false;
        }

        $submission = $this->get_submission($USER->id);
        $this->update_grade($submission);
        return true;
    }

    function preprocess_submission(&$submission) {
        if ($this->assignment->var1 && empty($submission->submissioncomment)) {  // comment inline
            if ($this->usehtmleditor) {
                // Convert to html, clean & copy student data to teacher
                $submission->submissioncomment = format_text($submission->data1, $submission->format);
                $submission->format = FORMAT_HTML;
            } else {
                // Copy student data to teacher
                $submission->submissioncomment = $submission->data1;
                $submission->format = $submission->format;
            }
        }
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array(0 => get_string('no'), 1 => get_string('yes'));


        $mform->addElement('select', 'resubmit', get_string("allowresubmit", "assignment"), $ynoptions);
        $mform->setHelpButton('resubmit', array('resubmit', get_string('allowresubmit', 'assignment'), 'assignment'));
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string("emailteachers", "assignment"), $ynoptions);
        $mform->setHelpButton('emailteachers', array('emailteachers', get_string('emailteachers', 'assignment'), 'assignment'));
        $mform->setDefault('emailteachers', 0);

        $mform->addElement('select', 'var1', get_string("commentinline", "assignment"), $ynoptions);
        $mform->setHelpButton('var1', array('commentinline', get_string('commentinline', 'assignment'), 'assignment'));
        $mform->setDefault('var1', 0);

        $mform->addElement('select', 'var2', get_string('numbofstudents', 'assignment_reflection'), 
                array(2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11, 12 => 12, 13 => 13, 14 => 14, 15 => 15));
        $mform->setDefault('var2', 4);
    }

    function print_student_answer($userid, $return=false) {
        global $CFG;
        if (!$submission = $this->get_submission($userid)) {
            return '';
        }
        $output = '<div class="files">' .
                '<img src="' . $CFG->pixpath . '/f/html.gif" class="icon" alt="html" />' .
                link_to_popup_window('/mod/assignment/type/reflection/file.php?id=' . $this->cm->id . '&amp;userid=' .
                        $submission->userid, 'file' . $userid, shorten_text(trim(strip_tags(format_text($submission->data1, $submission->format))), 15), 450, 580,
                        get_string('submission', 'assignment'), 'none', true) .
                '</div>';
        return $output;
    }

    function print_user_files($userid, $return=false) {
        global $CFG;

        if (!$submission = $this->get_submission($userid)) {
            return '';
        }

        $output = '<div class="files">' .
                '<img align="middle" src="' . $CFG->pixpath . '/f/html.gif" height="16" width="16" alt="html" />' .
                link_to_popup_window('/mod/assignment/type/reflection/file.php?id=' . $this->cm->id . '&amp;userid=' .
                        $submission->userid, 'file' . $userid, shorten_text(trim(strip_tags(format_text($submission->data1, $submission->format))), 15), 450, 580,
                        get_string('submission', 'assignment'), 'none', true) .
                '</div>';


        print_simple_box_start('center', '', '', 0, 'generalbox', 'wordcount');
        /// Decide what to count
        if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_WORDS) {
            echo ' (' . get_string('numwords', '', count_words(format_text($submission->data1, $submission->format))) . ')';
        } else if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_LETTERS) {
            echo ' (' . get_string('numletters', '', count_letters(format_text($submission->data1, $submission->format))) . ')';
        }
        print_simple_box_end();
        print_simple_box(format_text($submission->data1, $submission->format), 'center', '100%');

    }


    function display_ready_submissions() {
        global $CFG, $db, $USER;
        require_once($CFG->libdir.'/gradelib.php');
     
        if (isset($_POST['updatepref'])){
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage ;
            set_user_preference('assignment_perpage', $perpage);
            set_user_preference('assignment_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
        }

        $perpage    = get_user_preferences('assignment_perpage', 10);
        $quickgrade = get_user_preferences('assignment_quickgrade', 0);
        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page    = optional_param('page', 0, PARAM_INT);
        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet

        add_to_log($course->id, 'assignment_reflection', 'view submission', 'submissions.php?id='.$this->cm->id, $this->assignment->id, $this->cm->id);

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $users = get_fieldset_select("assignment_submissions", "userid", "assignment = {$this->cm->instance} AND SUBSTRING(data2, 34, 1) = 2 AND grade < 0");
        $tablecolumns = array('picture', 'fullname', 'grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade');

        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $tableheaders = array('',
                              get_string('fullname'),
                              get_string('grade'),
                              get_string('comment', 'assignment'),
                              get_string('lastmodified').' ('.$course->student.')',
                              get_string('lastmodified').' ('.$course->teacher.')',
                              get_string('status'),
                              get_string('finalgrade', 'grades'));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir.'/tablelib.php');

        $table = new flexible_table('mod-assignment-submissions');
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/assignment/view.php?id='.$this->cm->id);
        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(false);
        $table->column_suppress('picture');
        $table->column_suppress('fullname');
        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }
        $table->set_attribute('cellspacing', '1');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');
        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');

        $table->setup();
		# -----------------------------------------------		

		$ungroupedusers = get_fieldset_select("assignment_submissions", "userid", "assignment = {$this->cm->instance} AND data2 = '0'");

		if($ungroupedusers){			
			print_box_start('generalbox boxwidthwide boxaligncenter', 'reflection');
			print_heading(get_string('waitingforgroup', "assignment_reflection"));
			echo '<ul>';
			foreach ($ungroupedusers as $ungroupeduserid) {
				$u = get_record('user', 'id', $ungroupeduserid);
				echo '<li><a href="'.$CFG->wwwroot.'/user/view.php?id='.$u->id.'&course='.$this->course->id.'">'.$u->firstname.' '.$u->lastname.'</a></li>';
			}
			echo '</ul>';
			print_box_end();
		}else {
			print_heading(get_string('nostudentswaitingforgroup', 'assignment_reflection'));
		}

		# -----------------------------------------------
		
        if (empty($users)) {           
            print_heading(get_string('nostudentsready', "assignment_reflection"));
            return true;
        }
        else {
            print_heading(get_string('studentreadyforgrading', 'assignment_reflection'));
        }


        if ($where = $table->get_sql_where()) {
            $where .= ' AND ';
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $select = 'SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                          s.id AS submissionid, s.grade, s.submissioncomment,
                          s.timemodified, s.timemarked,
                          COALESCE(SIGN(SIGN(s.timemarked) + SIGN(s.timemarked - s.timemodified)), 0) AS status ';
        $sql = 'FROM '.$CFG->prefix.'user u '.
               'LEFT JOIN '.$CFG->prefix.'assignment_submissions s ON u.id = s.userid
                                                                  AND s.assignment = '.$this->assignment->id.' '.
               'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

        $table->pagesize($perpage, count($users));

        ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
        $offset = $page * $perpage;

        $strupdate = get_string('update');
        $strgrade  = get_string('grade');
        $grademenu = make_grades_menu($this->assignment->grade);

        if (($ausers = get_records_sql($select.$sql.$sort, $table->get_page_start(), $table->get_page_size())) !== false) {
            $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array_keys($ausers));
            foreach ($ausers as $auser) {
                $final_grade = $grading_info->items[0]->grades[$auser->id];
                $grademax = $grading_info->items[0]->grademax;
                $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                $locked_overridden = 'locked';
                if ($final_grade->overridden) {
                    $locked_overridden = 'overridden';
                }

            /// Calculate user status
                $auser->status = ($auser->timemarked > 0) && ($auser->timemarked >= $auser->timemodified);
                $picture = print_user_picture($auser, $course->id, $auser->picture, false, true);

                if (empty($auser->submissionid)) {
                    $auser->grade = -1; //no submission yet
                }

                if (!empty($auser->submissionid)) {
                ///Prints student answer and student modified date
                ///attach file or print link to student answer, depending on the type of the assignment.
                ///Refer to print_student_answer in inherited classes.
                    if ($auser->timemodified > 0) {
                        $studentmodified = '<div id="ts'.$auser->id.'">'.$this->print_student_answer($auser->id)
                                         . userdate($auser->timemodified).'</div>';
                    } else {
                        $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    }
                ///Print grade, dropdown or text
                    if ($auser->timemarked > 0) {
                        $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                                     'menu['.$auser->id.']', $auser->grade,
                                                     get_string('nograde'),'',-1,true,false,$tabindex++);
                            $grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }

                    } else {
                        $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                                     'menu['.$auser->id.']', $auser->grade,
                                                     get_string('nograde'),'',-1,true,false,$tabindex++);
                            $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }
                    }
                ///Print Comment
                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                    } else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                                 . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                 . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                    } else {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),15).'</div>';
                    }
                } else {
                    $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                    $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                    if ($final_grade->locked or $final_grade->overridden) {
                        $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                    } else if ($quickgrade) {   // allow editing
                        $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                                 'menu['.$auser->id.']', $auser->grade,
                                                 get_string('nograde'),'',-1,true,false,$tabindex++);
                        $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                    } else {
                        $grade = '<div id="g'.$auser->id.'">-</div>';
                    }

                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                    } else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                                 . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                 . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                    } else {
                        $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                    }
                }

                if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                    $auser->status = 0;
                } else {
                    $auser->status = 1;
                }

                $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;

                $popup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
                           . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;offset='.$offset++;
                $button = link_to_popup_window ($popup_url, 'grade'.$auser->id, $buttontext, 600, 780,
                                                $buttontext, 'none', true, 'button'.$auser->id);

                $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

                $finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

                $outcomes = '';

                if ($uses_outcomes) {

                    foreach($grading_info->outcomes as $n=>$outcome) {
                        $outcomes .= '<div class="outcome"><label>'.$outcome->name.'</label>';
                        $options = make_grades_menu(-$outcome->scaleid);

                        if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                            $options[0] = get_string('nooutcome', 'grades');
                            $outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
                        } else {
                            $outcomes .= ' ';
                            $outcomes .= choose_from_menu($options, 'outcome_'.$n.'['.$auser->id.']',
                                        $outcome->grades[$auser->id]->grade, get_string('nooutcome', 'grades'), '', 0, true, false, 0, 'outcome_'.$n.'_'.$auser->id);
                        }
                        $outcomes .= '</div>';
                    }
                }

		$userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser, has_capability('moodle/site:viewfullnames', $this->context)) . '</a>';
                $row = array($picture, $userlink, $grade, $comment, $studentmodified, $teachermodified, $status, $finalgrade);
                if ($uses_outcomes) {
                    $row[] = $outcomes;
                }

                $table->add_data($row);
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade){
            echo '<form action="view.php" id="fastg" method="post">';
            echo '<div>';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="mode" value="fastgrade" />';
            echo '<input type="hidden" name="page" value="'.$page.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '</div>';
        }

        $table->print_html();  /// Print the whole table

        if ($quickgrade){
            $lastmailinfo = get_user_preferences('assignment_mailinfo', 1) ? 'checked="checked"' : '';
            echo '<div class="fgcontrols">';
            echo '<div class="emailnotification">';
            echo '<label for="mailinfo">'.get_string('enableemailnotification','assignment').'</label>';
            echo '<input type="hidden" name="mailinfo" value="0" />';
            echo '<input type="checkbox" id="mailinfo" name="mailinfo" value="1" '.$lastmailinfo.' />';
            helpbutton('emailnotification', get_string('enableemailnotification', 'assignment'), 'assignment').'</p></div>';
            echo '</div>';
            echo '<div class="fastgbutton"><input type="submit" name="fastg" value="'.get_string('saveallfeedback', 'assignment').'" /></div>';
            echo '</div>';
            echo '</form>';
        }
        /// End of fast grading form

        /// Mini form for setting user preference
        echo '<div class="qgprefs">';
        echo '<form id="options" action="view.php?id='.$this->cm->id.'" method="post"><div>';
        echo '<input type="hidden" name="updatepref" value="1" />';
        echo '<table id="optiontable">';
        echo '<tr><td>';
        echo '<label for="perpage">'.get_string('pagesize','assignment').'</label>';
        echo '</td>';
        echo '<td>';
        echo '<input type="text" id="perpage" name="perpage" size="1" value="'.$perpage.'" />';
        helpbutton('pagesize', get_string('pagesize','assignment'), 'assignment');
        echo '</td></tr>';
        echo '<tr><td>';
        echo '<label for="quickgrade">'.get_string('quickgrade','assignment').'</label>';
        echo '</td>';
        echo '<td>';
        $checked = $quickgrade ? 'checked="checked"' : '';
        echo '<input type="checkbox" id="quickgrade" name="quickgrade" value="1" '.$checked.' />';
        helpbutton('quickgrade', get_string('quickgrade', 'assignment'), 'assignment').'</p></div>';
        echo '</td></tr>';
        echo '<tr><td colspan="2">';
        echo '<input type="submit" value="'.get_string('savepreferences').'" />';
        echo '</td></tr></table>';
        echo '</div></form></div>';
        ///End of mini form
    }
}

class mod_assignment_reflection_edit_form extends moodleform {

    function definition() {
        $mform = & $this->_form;

        // visible elements
        $mform->addElement('htmleditor', 'text', get_string('submission', 'assignment'), array('cols' => 60, 'rows' => 30));
        $mform->setType('text', PARAM_RAW); // to be cleaned before display
        $mform->setHelpButton('text', array('reading', 'writing', 'richtext'), false, 'editorhelpbutton');
        $mform->addRule('text', get_string('required'), 'required', null, 'client');

        $mform->addElement('format', 'format', get_string('format'));
        $mform->setHelpButton('format', array('textformat', get_string('helpformatting')));

        // hidden params
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // buttons
        $this->add_action_buttons();
    }
}

?>