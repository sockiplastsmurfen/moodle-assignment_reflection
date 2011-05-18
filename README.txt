==Description==
The Reflection Assignment Type allows the teacher to specify how many students a reflection group will have. The students will be asked to read a text and then write a reflection about it. The student can then submit their reflection. When the amount of submitted reflections equals the number of group members, a new forum will be created and the submitted reflections will be added as topics. The students can now give comments on each others reflections, and this will hopefully generate a discussion. When at least one comment is made on each of the other group members reflections the student will be able to confirm them self ready for grading. When the teacher enters the assignment, a list with the students who are ready to be graded will be shown. The student will be removed from this list when he/she is graded. This makes it easier for the teacher to manage large classes. Student can view their grades and feedbacks within the assignment.

To make sure you get the latest version download it from here:
* [https://github.com/sockiplastsmurfen/moodle-assignment_reflection/zipball/MOODLE_19_STABLE Download Module for Moodle 1.9]

=== Changelog ===
==== v. 2011051800 ====
'''assignment.class.php'''
* Removed hardcoded module id and made it dynamic.
* Changed all redirection times to 0 sec
* Added list of students who are waiting for a group in teacher view.
* Added link to Forum for students who are waiting for grading or have received a grade.

'''file.php'''
* Added a link to Forum in the grading popup window.
* Added a "Recompletion button" in the grading popup window.

'''lang/en_utf8/assignment_reflection.php''' and '''lang/sv_utf8/assignment_reflection.php'''
* Added some language strings.

'''README.txt'''
* Added readme document.

'''version.php'''
* Updated plugin version number.

== Features ==
* Allows teacher to specify how many students a reflection group will have
* Support resubmit until a forum has been created
* Easy installation
* Automatic generation of group and groupings
* Automatic generation of forums and topics
* Translation: English and Swedish

== Benefits ==
* Less administration for teachers
* Learning community
* Simplified grading process for large classes

== Installation ==
# Unzip the files to <code>/mod/assignment/type/reflection</code>
# Visit the notifications page and the module will install.
IMPORTANT! This module relies on the groupings function.
   
To enable it go to <code>Miscellaneous -> Experimental</code> and check the ”<code>Enable groupings</code>” box.

== Setting up a Reflection assignment ==
# Select Reflection under Assignments from the Activities menu on the course page.
# Enter the General Assignment settings and set the amount of students in each group. Put the text (or a link to it) that the student should write a reflection about in the description. If the text is long its recommended to add the text to the course page and just add a link to it.

== Credits ==
This project is sponsored by [http://dsv.su.se/en/ DSV] at Stockholm Stockholm university.

== Todo ==
* Support for document submission
* Make the ”ready for grading button” optional

== Links ==
* [http://moodle.org/mod/data/view.php?d=13&rid=4356&filter=1 Moodle Modules Posting] (Please add comments here)
* [http://tracker.moodle.org/browse/CONTRIB/component/10762 Bug Tracker Page] (Please report bugs here)
* [http://moodle.org/mod/forum/discuss.php?d=162370 Discussion]
* [https://github.com/sockiplastsmurfen/moodle-assignment_reflection/tree/MOODLE_19_STABLE Git Repository for Moodle 1.9]
* [https://github.com/sockiplastsmurfen/moodle-assignment_reflection/zipball/MOODLE_19_STABLE Download Module for Moodle 1.9]
'''Obsolete'''
* [http://cvs.moodle.org/contrib/plugins/mod/assignment/type/reflection/ Obsolete: CVS Repository]
* [http://download.moodle.org/download.php/plugins/mod/assignment/type/reflection.zip Obsolete: Download Module]
== UML ==
[[Image:ReflectionUML.png|Reflection UML]]

[[Category:Contributed code]]
