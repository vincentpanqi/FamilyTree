<?php

/**
 * Suggestion Class is used to add, remove or edit suggestions
 * Basically a Class to operate on suggested_info and suggest_approval table
 * @extends member_operation_suggest
 * @author piyush
 */
require_once 'member_operation_suggest.php';

class suggest extends member_operation_suggest {

    public $id, $suggested_value, $typesuggest, $suggestedby, $detail;

    /**
     * Constructor of the class. This gathers the basic information about
     * the suggestion which is to be managed
     * @global \db $db Instance of the db class
     * @param integer $suggestid The ID of the suggestion to be managed
     */
    function __construct($suggestid) {
        global $db;
        $this->id = $suggestid;
        $row = $db->get("select * from suggested_info where id=$suggestid");
        $this->detail = $row;
    }

    /**
     * This function is used to add approval to this suggestion. Returns false
     * on error
     * @global \db $db Instance of the db class
     * @global \user $user Instance of the user class
     * @return boolean
     */
    function approve($forceful = false) {
        global $db, $user;

        if ($forceful) {
            $this->apply();
            return true;
        }
        if (!$db->get("Insert into suggest_approved(suggest_id,user_id,action) values($this->id, 
                " . $user->user['id'] . ",1)")) {
            return false;
        }

        //Check if suggestion has crossed 50% Mark
        $this->check_decision();
        return true;
    }

    /**
     * This function is used to add rejection to the suggestion. Return false
     * on error
     * @global \db $db Instance of the db class
     * @global \user $user Instance of user class
     * @return boolean
     */
    function reject($forceful = false) {
        //Rejects the $id provided in the constructor
        global $db, $user;

        if ($forceful) {
            $this->removesuggestion();
            return true;
        }
        if (!$db->get("Insert into suggest_approved (suggest_id,user_id,action) values
            ($this->id," . $user->user[0] . ",0)")) {
            return false;
        }

        //Check if suggestion has crossed 50% mark
        $this->check_decision();
        return TRUE;
    }

    /**
     * This function is to check the percentage of the approval/rejection/dontknow
     * of this suggestion
     * @global \db $db Instance of the db class
     * @return boolean
     */
    private function checkpercent() {
        global $db;

        //Get all Rejections, Approvals, Dontknow's
        $query = $db->query("select * from suggest_approved where suggest_id=" . $this->id);
        $row2 = $db->get('select count(*) as totaluser from member where username!="" and password!=""');
        $total = mysql_num_rows($query);
        $noapproved = 0;
        $norejected = 0;

        //Count the no of approvals/Rejections
        while ($row = $db->fetch($query)) {
            switch (intval($row['action'])) {
                case 0:$norejected++;
                    break;
                case 1:$noapproved++;
                    break;
                default:
                    break;
            }
        }
        $noapproved = ($noapproved / $total) * 100;
        $norejected = ($norejected / $total) * 100;

        //If approved>50 then accept the suggestion
        //if rejected>50 then reject the suggestion
        if ($total == $row2['totaluser']) {
            return array($noapproved, $norejected);
        } else {
            return false;
        }
    }

    /**
     * This function is used to take a decision whether to approve the suggestion
     * , reject it on the basis percentage of approval or rejection
     * @return null
     */
    private function check_decision() {
        $percent = $this->checkpercent();

        if ($percent) {
            if ($percent[0] > 10) {
                //Almost half the people have agreed, So lets add it permanently..
                $this->apply();
            } else if ($percent[1] > 10) {
                //More than half of the people have rejected it, So lets remove the suggestion
                $this->removesuggestion();
            }
        }
    }

    private function removesuggestion() {
        //Remove the suggestion
        global $db;

        $query = $db->get("delete from suggested_info where id = " . $this->id);

        if ($query) {
            $this->approval_delete();
        }
    }

    /**
     * This function is used to accept the suggestion and apply the changes to the 
     * main member table
     * @global \vanshavali $vanshavali Instance of the vanshavali class
     * @global \db $db Instance of the db class
     * @return null
     */
    private function apply() {
        global $db, $suggest_handler;

        //Hand over the Functions to the handler
        $suggest_handler->apply_suggest($this->detail);

        //Now delete all the suggestion approvals as they are of no use
        $this->approval_delete();

        //Now mark the suggestion as applied So that it can be used in future
        $db->get("update suggested_info set approved=1 where id=$this->id");
    }

    /**
     * This function is used to delete all the data regarding the suggestion approval
     * or rejection. This is to be used when the suggestion is applied and user votes
     * are of no use. Although it is automatically invoked.
     * @global \db $db
     * @return boolean
     */
    function approval_delete() {
        global $db;

        if ($db->get("Delete from suggest_approved where suggest_id=$this->id")) {
            return TRUE;
        } else {
            return false;
        }
    }

}

?>
