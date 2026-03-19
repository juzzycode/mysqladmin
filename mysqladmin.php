<?php
// Original code written for managing mysql tables via a UI as a quick admin interface. Not meant to be a full featured admin tool, but rather a quick and dirty way to manage tables without phpmyadmin or writing custom sql queries.

class MysqlAdmin {

        public $table;                                          // Table to display admin tools for - REQUIRED
        public $where;                                          // Where clause to enforce - REQUIRED
        public $keyfield;                                       // Main id to select from in edit mode, must be unique - REQUIRED
        public $showfields = array('*');        // Which columns to display 
        public $excludefields = array();        // Which columns to hide
        public $protectedfields;                        // Which columns to ensure the value is set, in add/edit
        public $donotadd;                                       // Columns to not include in an add statement (like timestamp) - takes array()
        public $disableedit;                            // display only, disables href edit and add form
        public $disableadd;                             // disables add form
        public $ordering;                                       // which columns sets order by
        public $link;                                           // Very powerful, create combo boxes based off another query
        public $append;                                         // add values to combo boxes
        public $nodelete;                                       // remove delete options
        public $prelisttext;                            // Display plain text before a list of items, helpful if multiple admin variable are being used on the same page
        public $label;                                          // rename a column to a more suitable name, or capitalize
        public $maxentries=999999;                      // if 0, show add, if >= max, remove add.
        public $grouplabel;                                     // Never implemented
        public $timestamp;                                      // identify a column as a timestamp
        public $readonlyfields = array();       // disable the input box
        public $mce;                                            // mce editor
        public $goback;                                         // link for a back button
        public $replacelinks;                           // pass it a column and a function to pass the url to
        public $optionlabel;                            // pass it an id->label pairs to display other than id.

        private $id;
        private $count=0;

        function set($var,$val){
                $this->$var = $val;
        }

        /**
         * Main display function
         *
         * @return string
         */
        function display() {

                if (isset($_POST['mcetable']))
                        return $this->mcesave();
                if (isset($_GET['mce']))
                        return $this->mceedit();
                if (isset($_GET['move']))
                        return $this->move();
                if (isset($_GET['edit']))
                        return $this->edit();
                if (isset($_POST['delete']) && !isset($this->nodelete))
                        return $this->delete();
                if (isset($_POST['save']))
                        return $this->save();
                if (isset($_POST['add']))
                        return $this->add();

                return $this->listtable();
}
        // group will allow sub categories and grouping
        function group($group){
                if (isset($_GET['group'])) {
                        if (empty($this->where)) {
                                $this->where = "$group = '$_GET[group]'";
                        } else {
                                $this->where .= " and $group = '$_GET[group]'";
                        }
                        return $this->display();
                }

                $table=$this->table;
                $where=$this->where;
                $keyfield=$this->keyfield;
                $showfields=$this->showfields;
                $excludefields=$this->excludefields;
                $donotadd=$this->donotadd;
                $label=$this->label;

                if ($where != '')
                        $where = "WHERE $where";

                $query = "SELECT distinct $group FROM $table $where";
                echo $query;
                $res = mysql_query($query)
                        or die($query . ":<br>". mysql_error());
                $list = <<<EOF
                        <div class="table">
                                <img src="images/bg-th-left.gif" width="8" height="7" alt="" class="left" />
                                <img src="images/bg-th-right.gif" width="7" height="7" alt="" class="right" />
                                <table class="listing form" cellpadding="0" cellspacing="0" border=0><tr><th class="full" colspan="2">$this->grouplabel</th>
EOF;

                $odd = 1;
                //Create list of existing records
                while ($row = mysql_fetch_array($res)) {
                        $oddeven = ($odd++ % 2) ? "oddList" : "evenList";
                        $list .= "</tr>\n<tr class='$oddeven'>";
                        $list .= "\t\t\t<td><a href='$_SERVER[REQUEST_URI]&group=$row[$group]'>".htmlentities($row['menuname'])."</a></td>\n";
                }
                $list .= "</tr>\n\t\t</table>\n\t</div>\n";
                return $list;
        }

// Default
        function listtable() {
                //global $seed;
                $table=$this->table;
                $where=$this->where;
                $keyfield=$this->keyfield;
                $showfields=$this->showfields;
                $excludefields=$this->excludefields;
                $donotadd=$this->donotadd;
                $label=$this->label;

                $_SESSION['sqladminid'] = 0;
                $list = '';
                $fields = '';
                $url = explode("&",$_SERVER['REQUEST_URI']);

                if ($where != '')
                        $where = "WHERE $where";

                foreach ($showfields as $field)
                        $fields .= "$field,";

                if (!empty($this->ordering)&& !in_array($this->ordering,$showfields))
                                $fields .= $this->ordering.",";

                $fields .= "{$keyfield} AS ourkeyfield";

                $ordering='';
                if (!empty($this->ordering)) {
                        $ordering = "order by ". $this->ordering;
                }

                //main select
                $query = "SELECT $fields FROM $table $where $ordering";
                //echo $query;
                $res = mysql_query($query)
                        or die($query . ":<br>". mysql_error());

                $fields = mysql_num_fields($res);
                $fields--;//for the key

                $coltype = $this->gettablecols($table);
                //print_r($coltype); enum('mls','content','tracker')
                //form for a new record
                $rowtypes = array();
                $addtable = '
                        <div class="table">
                                <img src="images/bg-th-left.gif" width="8" height="7" alt="" class="left" />
                                <img src="images/bg-th-right.gif" width="7" height="7" alt="" class="right" />';
                if (isset($url[1]))
                        $addtable .= "
                                <form action='$url[0]&$url[1]' method='post'>
                                <input type=hidden name='add' value='add'><table class=\"listing form\" cellspacing=0 cellpadding=0 border=0 width=613>";
                else 
                        $addtable .= "
                                <form action='$url[0]' method='post'>
                                <input type=hidden name='add' value='add'><table class=\"listing form\" cellspacing=0 cellpadding=0 border=0 width=613>";

                $addtable .= '
                                        <tr>
                                                <th class="full" colspan="2">Add an Item</th>
                                        </tr>';
                $list = <<<EOF
                        <div class="table">
                                <img src="images/bg-th-left.gif" width="8" height="7" alt="" class="left" />
                                <img src="images/bg-th-right.gif" width="7" height="7" alt="" class="right" />
                                <table class="listing form" cellpadding="0" cellspacing="0" border=0><tr>
EOF;
                //field names (header)
                $odd2 = 1;
                $listfirst=1;
                for ($i=0; $i < $fields; $i++) {
                        $fieldname[] =  mysql_field_name($res,$i);
                        if ($fieldname[$i] == $keyfield) continue;
                        if (!empty($excludefields) && in_array($fieldname[$i],$excludefields)) continue;
                        if (!empty($this->mce) && in_array($fieldname[$i],$this->mce)) continue;

                    //$type  = mysql_field_type($res, $i);
                    //$firstword = str_word_count($type,1);
                        //$rowtypes[] = $firstword[0];

                        $fieldclean = $fieldname[$i];

                        if (!empty($label) && isset($label[$fieldclean]))
                                $fieldclean = $label[$fieldclean];
                        if (isset($this->link) && isset($this->link[$fieldname[$i]])) {
                                $linkres = mysql_query($this->link[$fieldname[$i]]) or die(mysql_error() ."<br>". $this->link[$fieldname[$i]]);
                                $options = '';
                                while (list($option_index,$option) = @mysql_fetch_row($linkres))
                                        $options .= "<option value='$option_index'>$option</option>";

                                if (isset($this->append) && isset($this->append[$fieldclean])) {
                                        foreach ($this->append[$fieldclean] as $option)
                                                $options .= "<option>$option</option>";
                                }
                                $oddeven2 = ($odd2++ % 2) ? "oddList" : "evenList";
                                $addtable .= "\t\t<tr class='$oddeven2'><td class='first'>$fieldclean:</td><td class='last'><select name='$fieldname[$i]'>$options</select></td></tr>\n";
                                if ($listfirst) {
                                        $list .= '<th class="first" width="177">' . $fieldclean . '</th>';
                                        $listfirst=0;
                                } else {
                                        $list .= '<th>' . $fieldclean . '</th>';
                                }

                                continue;
                        }
                // addtable is for the form at the bottom
                                if ($listfirst) {
                                        $list .= '<th class="first" width="177">' . $fieldclean . '</th>';
                                        $listfirst=0;
                                } else {
                                        $list .= '<th>' . $fieldclean . '</th>';
                                }
                        if (in_array($fieldname[$i],$this->readonlyfields))
                                continue;
                        if (!empty($donotadd) && in_array($fieldname[$i],$donotadd)) continue;
                        //print($coltype[$fieldname[$i]]);
                                $oddeven2 = ($odd2++ % 2) ? "oddList" : "evenList";
                        if (stristr($coltype[$fieldname[$i]],'int') || stristr($coltype[$fieldname[$i]],'double') 
                                || stristr($coltype[$fieldname[$i]],'float') || stristr($coltype[$fieldname[$i]],'decimal')) {
                                $addtable .= "<tr class='$oddeven2'><td  class='first'>$fieldclean:</td><td class='last'><input type='text' name='$fieldname[$i]' value='' size=5></td></tr>\n";
                        } elseif (stristr($coltype[$fieldname[$i]],'enum')){
                                $values = str_ireplace('enum(','',$coltype[$fieldname[$i]]);
                                $values = str_ireplace(')','',$values);
                                $values = str_ireplace("'",'',$values);
                                $options='';
                                foreach (explode(',',$values) as $value){
                                        $options .= "<option>$value</option>";
                                }
                                $addtable .= "\t\t<tr class='$oddeven2'><td class='first'>$fieldclean:</td><td class='last'><select name='$fieldname[$i]'>$options</select></td></tr>\n";
                        } elseif (stristr($coltype[$fieldname[$i]],'text')){
                                $addtable .= "\t\t<tr class='$oddeven2'><td class='first'>$fieldclean:</td><td class='last'><textarea cols=40 rows=6 name='$fieldname[$i]'></textarea></td></tr>\n";
                        } else
                                $addtable .= "\t\t<tr class='$oddeven2'><td class='first'>$fieldclean:</td><td class='last'><input type='text' name='$fieldname[$i]' value='' size=35></td></tr>\n";
                }
                $oddeven2 = ($odd2++ % 2) ? "oddList" : "evenList";
                $addtable .= "</tr>\n<tr class='$oddeven2'><td colspan=2 align=right><input type=submit value='Add new record'></td></tr></table></form></div>";
                $list = str_replace("<th>$fieldclean","<th class='last'>$fieldclean",$list);


                $o=0;
                $odd = 1;
                //Create list of existing records
                while ($row = mysql_fetch_array($res)) {
                        $oddeven = ($odd++ % 2) ? "oddList" : "evenList";
                        $this->count++;
                        $list .= "</tr>\n<tr class='$oddeven'>";
                        for ($i=0; $i < $fields; $i++) {
                                if ($fieldname[$i] == $keyfield) continue;
                                if (!empty($excludefields) && in_array($fieldname[$i],$excludefields)) continue;
                                if (!empty($this->mce) && in_array($fieldname[$i],$this->mce)) continue;
                                //add arrows for ordering
                                if (!empty($this->ordering) && $fieldname[$i] == $this->ordering){
                                        //$_SESSION['key']=substr(md5($seed . $row['ourkeyfield']),0,8);
                                        if ($o) {
                                                $list .= "\t\t\t<td><a href='$_SERVER[REQUEST_URI]&amp;edit=$row[ourkeyfield]&move=up'><img border=0 src='images/Up.gif'  height=16></a> 
                                                <a href='$_SERVER[REQUEST_URI]&amp;edit=$row[ourkeyfield]&move=down'><img border=0 src='images/Down.gif' height=16></a></td>\n";
                                        } else {
                                                $list .= "\t\t\t<td><a href='$_SERVER[REQUEST_URI]&amp;edit=$row[ourkeyfield]&move=down'><img border=0 src='images/Down.gif' height=16></a></td>\n";
                                        }
                                        $o++;
                                } else {
                                        if (strlen($row[$i]) > 80) {
                                                //fix for html/comments
                                                if (isset($this->disableedit))
                                                        $list .= "\t\t\t<td>".substr(htmlentities($row[$i]),0,80)."...</td>\n";
                                                else
                                                        $list .= "\t\t\t<td><a href='$_SERVER[REQUEST_URI]&amp;edit=$row[ourkeyfield]'>".substr(htmlentities($row[$i]),0,80)."...</a></td>\n";
                                        } else {
                                                if (isset($this->disableedit))
                                                        $list .= "\t\t\t<td>". htmlentities($row[$i]) ."</td>\n";
                                                else
                                                        $list .= "\t\t\t<td><a href='$_SERVER[REQUEST_URI]&amp;edit=$row[ourkeyfield]'>".htmlentities($row[$i])."</a></td>\n";
                                        }
                                }
                        }
                }
                $list .= "</tr>\n\t\t</table>\n\t$this->prelisttext\n\t</div>\n";
                mysql_free_result($res);
                if (isset($this->disableedit) || isset($this->disableadd) || $this->count >= $this->maxentries)
                        return $list;
                if (isset($this->hideedit))
                        return $addtable;
                return $list . "<br><br>\n" . $addtable;

        }

        function edit() {
                $table=$this->table;
                $where=$this->where;
                $keyfield=$this->keyfield;
                $showfields=$this->showfields;
                $excludefields=$this->excludefields;
                $donotadd=$this->donotadd;
                $label=$this->label;

                //global $seed;
                $list = '';
                $fields = '';
                if (is_numeric($_GET['edit'])) {
                        $_SESSION['sqlid'] = $_GET['edit'];
                } else {
                        die("edit is not a number.");
                }
                if (isset($this->disableedit)) die("Editing this table is disabled.");

                //die($_SERVER['REQUEST_URI']);
                //$url = explode("&",$_SERVER['REQUEST_URI']);
                $url = preg_replace("/&edit.*/",'',$_SERVER['REQUEST_URI']);

                if ($where != '')
                        $where = "WHERE $where and $keyfield = '$_GET[edit]'";
                else 
                        $where = "WHERE $keyfield = '$_GET[edit]'";

                foreach ($showfields as $field)
                        $fields .= "$field,";

                $fields .= "$keyfield AS ourkeyfield";

                $query = "SELECT $fields FROM $table $where";
                $res = mysql_query($query)
                        or die($query . ":<br>". mysql_error());

                $fields = mysql_num_fields($res);
                $fields--;//for the key

                $rowtypes = array();
                $coltype = $this->gettablecols($table);

                //if (substr(md5($seed . $_GET['edit']),0,8) != $_GET['key']) {
                //      die("Nice try.");
                //}

                $list = "
                        <form action='$url' method='post'>
                        <input type=hidden name='save' value='$_GET[edit]'>";

                $list .= "
                <table border=2>
                <tr>
                        <td>
                                <table border=0>
                                <tr>
                                        <td><b>Name</b></td>
                                        <td><b>Value</b></td>
                        ";
                for ($i=0; $i < $fields; $i++) {
                    $type  = mysql_field_type($res, $i);
                    $firstword = str_word_count($type,1);
                        $rowtypes[] = $firstword[0];
                        $fieldname[] =  mysql_field_name($res,$i);
                }
                while ($row = mysql_fetch_array($res)) {
                        $list .= "</tr>\n";
                        for ($i=0; $i < $fields; $i++) {
                                /*VARCHAR
                                TEXT
                                DATE
                                DATETIME
                                TIMESTAMP
                                TIME
                                YEAR
                                CHAR
                                TINYBLOB
                                TINYTEXT
                                BLOB
                                MEDIUMBLOB
                                MEDIUMTEXT
                                LONGBLOB
                                LONGTEXT
                                ENUM
                                SET
                                BOOL
                                BINARY
                                VARBINARY*/
                                $fieldclean = $fieldname[$i];

                                if (!empty($label) && isset($label[$fieldclean]))
                                        $fieldclean = $label[$fieldclean];
                                if ($fieldname[$i] == $keyfield) continue;
                                if (!empty($excludefields) && in_array($fieldname[$i],$excludefields)) continue;
                                if (!empty($donotadd) && in_array($fieldname[$i],$donotadd)) continue;
                                if (in_array($fieldname[$i],$this->readonlyfields)) {
                                        $list .= "<tr><td>$fieldclean:</td><td>$row[$i]</td></tr>\n";
                                        continue;
                                }

                                //pull in options from a 2nd table
                                if (isset($this->link) && isset($this->link[$fieldname[$i]])) {
                                        $linkres = mysql_query($this->link[$fieldname[$i]]);
                                        $options = '';
                                        while (list($option_index,$option) = @mysql_fetch_row($linkres)) {
                                                $options .= "<option value='$option_index'";
                                                if ($option_index == $row[$i])
                                                        $options .= " selected";
                                                $options .= ">$option</option>\n\n";
                                        }

                                        if (isset($this->append) && isset($this->append[$fieldclean])) {
                                                foreach ($this->append[$fieldclean] as $option) {
                                                        $options .= "<option";
                                                        if ($option == $row[$i])
                                                                $options .= " selected";
                                                        $options .= ">$option</option>\n";
                                                }
                                        }

                                        $list .= "<tr><td>$fieldclean:</td><td><select name='$fieldname[$i]'>$options</select></td></tr>\n";
                                        continue;
                                }
                                if (stristr($rowtypes[$i],'int') || stristr($rowtypes[$i],'double') 
                                || stristr($rowtypes[$i],'float') || stristr($rowtypes[$i],'decimal')) {
                                        $list .= "<tr><td>$fieldclean:</td><td><input type='text' name='$fieldname[$i]' value='$row[$i]' size=5></td></tr>\n";
                                } elseif (is_array($this->mce) && in_array($fieldname[$i],$this->mce)) {
                                        $list .="<tr><td>$fieldclean:</td><td><a href='$url&mce=$fieldname[$i]'><img src='images/edit-icon.gif'> Edit in editor</a></td></tr>\n";
                                } elseif (strstr($coltype[$fieldname[$i]],'text')){
                                        $list .= "<tr><td>$fieldclean:</td><td><textarea cols=50 rows=6 name='$fieldname[$i]'>$row[$i]</textarea></td></tr>\n";
                                } elseif (strstr($row[$i],'<')) {
                                        $list .= "<tr><td>$fieldclean:</td><td><textarea name='$fieldname[$i]' cols=50 rows=4>$row[$i]</textarea></td></tr>\n";
                                } elseif (stristr($coltype[$fieldname[$i]],'enum')){
                                $values = str_ireplace('enum(','',$coltype[$fieldname[$i]]);
                                $values = str_ireplace(')','',$values);
                                $values = str_ireplace("'",'',$values);
                                $options='';
                                foreach (explode(',',$values) as $value){
                                        $options .= "<option";
                                        if ($value == $row[$i])
                                                $options .= " selected";
                                        $options .= ">$value</option>";
                                }
                                $list .= "<tr><td>$fieldclean:</td><td><select name='$fieldname[$i]'>$options</select></td></tr>\n";
                        } else {
                                        //if (!strstr($row[$i],"\\'")) {
                                                $cleanvalue = htmlspecialchars($row[$i],ENT_QUOTES);
                                        //} else { 
                                        //      $cleanvalue = $row[$i];
                                        //}
                                        $list .= "<tr><td>$fieldclean:</td><td><input type='text' name='$fieldname[$i]' value='$cleanvalue' size=35></td></tr>\n";
                                }
                        }
                }
                $delete="<input type=submit name='delete' value=Delete>";
                if (isset($this->nodelete)) {
                        $delete='';
                }
                $list .= "</tr>\n<tr><td colspan=2 align=right> <input type=submit value=Save> &nbsp; &nbsp; &nbsp;$delete </td></tr></table></td></tr></table></form>";
                mysql_free_result($res);
                return $list;
        }

        function save() {
                //global $seed;
                $table=$this->table;
                $where=$this->where;
                $keyfield=$this->keyfield;
                $showfields=$this->showfields;
                $excludefields=$this->excludefields;
                $donotadd=$this->donotadd;
                $fields = '';
                $fieldnames = '';
                /*
                if (substr(md5($seed . $_POST['save']),0,8) != $_POST['key']) {
                        die("Nice try.");
                }*/
                if ($_SESSION['sqlid'] != $_POST['save']) {
                        die("Something weird happened. EDIT and SAVE are out of sync. Possible hijack/xss attack.");
                }
                if (isset($this->disableedit)) die("Editing this table is disabled.");

                if ($where != '')
                        $where = "WHERE $where and $keyfield = '$_POST[save]'";
                else 
                        $where = "WHERE $keyfield = '$_POST[save]'";
                /*
                if (isset($_POST["delete"])) {
                        $query = "DELETE FROM $table where $keyfield = '$_POST[save]'";
                        die($query);
                        q($query);
                        return "Entry Deleted.";
                }*/
                foreach ($showfields as $field)
                        $fields .= "$field,";

                $fields .= "$keyfield AS ourkeyfield";
                $query = "SELECT $fields FROM $table $where"; 
                $res = mysql_query($query)
                        or die($query . ":<br>". mysql_error());

                $fields = mysql_num_fields($res);
                $fields--;
                for ($i=0; $i < $fields; $i++) {
                        $fieldname[] =  mysql_field_name($res,$i);
                }
                foreach($fieldname as $field) {
                        if ($field == $keyfield) continue;
                        if (!empty($excludefields) && in_array($field,$excludefields)) continue;
                        if (!empty($donotadd) && in_array($field,$donotadd)) continue;
                        if (is_array($this->mce) && in_array($field,$this->mce)) continue;
//                      print_r($this->replacelinks);
                        if (is_array($this->replacelinks) && isset($this->replacelinks[$field])) {

                                preg_match_all('/http[^\\\\"\'> \n]*/', $_POST[$field], $urls, PREG_SET_ORDER);
                                $value = $_POST[$field];
                                foreach($urls as $u){

                                        if (strstr($u[0],'firewater')) continue;
                                        $newurl = $this->replacelinks[$field]($field,$u[0]);
                                        $value = str_replace($u[0],$newurl,$value);
                                        //echo "$u[0], $newurl<br>";
                                        //die();
                                }
                                //die();
                                $fieldnames .= "`$field` = '$value', ";
                        } else {
                                $fieldnames .= "`$field` = '$_POST[$field]', ";
                        }
                }
                $fieldnames = trim($fieldnames,', ');

                $query = "UPDATE $table set $fieldnames where $keyfield = '$_POST[save]'";
                q($query);
                if (isset($this->goback) && !empty($this->goback)) {
                        header("Location: $this->goback");
                        exit;
                }
                return "Entry saved.";
        }
        function add() {
                //fixme needs to add with unique key too

                $table=$this->table;
                $keyfield=$this->keyfield;
                $showfields=$this->showfields;
                $excludefields=$this->excludefields;
                $protectedfields=$this->protectedfields;
                $donotadd=$this->donotadd;

                $fields = '';
                $fieldnames = '';
                $fieldvalues = '';
                if (isset($this->disableedit)) die("Editing this table is disabled.");

                foreach ($showfields as $field)
                        $fields .= "$field,";
                $fields = trim($fields,', ');

                $query = "SHOW FIELDS FROM $table"; 
                $res = mysql_query($query)
                        or die($query . ":<br>". mysql_error());

                $fields = mysql_num_rows($res);

                while($array=mysql_fetch_array($res)) {
                        $field = $array['Field'];
                        //echo "<br>field: $field<br>";
                        if ($field == $keyfield) continue;// fix me only works with auto_increment

                        if (isset($protectedfields[$field])) {
                                //enforce protectfields
                                $fieldnames .= "`$field`, ";
                                $fieldvalues .= "'$protectedfields[$field]',";
                                continue;
                        }
                        //these after protected so you can exclude and protect and protect still works
                        if (isset($excludefields[$field])) continue;
                        if (isset($donotadd[$field])) continue;
                        if (!empty($excludefields) && in_array($field,$excludefields)) continue;
                        if (!empty($donotadd) && in_array($field,$donotadd)) continue;

                        if (is_array($this->replacelinks) && isset($this->replacelinks[$field])) {

                                preg_match_all('/http[^\\\\"\'> \n]*/', $_POST[$field], $urls, PREG_SET_ORDER);
                                $value = $_POST[$field];
                                foreach($urls as $u){

                                        if (strstr($u[0],'firewater')) continue;
                                        $newurl = $this->replacelinks[$field]($field,$u[0]);
                                        $value = str_replace($u[0],$newurl,$value);
                                        //echo "$u[0], $newurl<br>";
                                        //die();
                                }
                                //die();
                                $fieldnames .= "`$field`, ";
                                $fieldvalues .= "'$value',";
                        } else {
                                $fieldnames .= "`$field`, ";
                                if(!isset($_POST[$field])) $_POST[$field]='';
                                $fieldvalues .= "'$_POST[$field]',";
                        }
                }



                $fieldnames = trim($fieldnames,', ');
                $fieldvalues = trim($fieldvalues,', ');

                $query = "INSERT INTO $table ($fieldnames) VALUES ($fieldvalues)";
                q($query);
                if ($this->timestamp) {
                        q("UPDATE $table set $this->timestamp=now()");
                }
                //die($this->goback);
                if (isset($this->goback) && !empty($this->goback)) {
                        header("Location: $this->goback");
                        exit;
                }
                header("Location: $_SERVER[REQUEST_URI]");
                return "Entry Added.";
        }

        function delete() {
                //global $seed;
                $table=$this->table;
                $where=$this->where;
                $keyfield=$this->keyfield;
                if (isset($this->disableedit)) die("Editing this table is disabled.");

                if ($_SESSION['sqlid'] != $_POST['save']) {
                        die("Something weird happened. EDIT and SAVE/DELETE are out of sync. Possible hijack/xss attack.");
                }
                /*if (substr(md5($seed . $_POST['save']),0,8) != $_POST['key']) {
                        die("Nice try.");
                }*/
                if ($where != '')
                        $where = "WHERE $where and $keyfield = '$_POST[save]'";
                else 
                        $where = "WHERE $keyfield = '$_POST[save]'";
                if (isset($_POST["delete"])) {
                        $query = "DELETE FROM $table where $keyfield = '$_POST[save]'";
                        q($query);

                if (isset($this->goback) && !empty($this->goback)) {
                        header("Location: $this->goback");
                        exit;
                }

                        return "Entry Deleted.";
                }
                return "Nothing to do?";
        }
        function move() {
                //global $seed;
                /*if (substr(md5($seed . $_GET['edit']),0,8) != $_GET['key']) {
                        die("Nice try.");
                }*/
                $ordering = $this->ordering;
                $redir = preg_replace("/&edit.*/",'',$_SERVER['REQUEST_URI']);

                $id=$_GET['edit'];
                $last='';
                switch ($_GET['move']) {
                        case 'up':
                                $res = q("select {$this->keyfield},{$this->ordering} from {$this->table} where {$this->where} order by {$this->ordering}");
                                while ($a=fa($res)) {
                                        if ($a[$this->keyfield] == $id) {
                                                //switch last with this one
                                                //echo "Last: ".$last[$this->keyfield] ." , ".$last[$this->ordering];
                                                //echo "<br>This: ".$a[$this->keyfield] ." , ".$a[$this->ordering];
                                                if ($last[$this->ordering] == $a[$this->ordering]) {//bump them all up by 1
                                                        q("update {$this->table} set {$this->ordering}={$this->ordering}+1 where {$this->where} and {$this->ordering} >= ".$last[$this->ordering]);
                                                        q("update {$this->table} set {$this->ordering}=".$a[$this->ordering]." where {$this->where} and {$this->keyfield} = ".$last[$this->keyfield]);
                                                } else {
                                                        q("update {$this->table} set {$this->ordering}=".$last[$this->ordering]." where {$this->where} and {$this->keyfield} = ".$a[$this->keyfield]);
                                                        q("update {$this->table} set {$this->ordering}=".$a[$this->ordering]." where {$this->where} and {$this->keyfield} = ".$last[$this->keyfield]);
                                                }
                                        } else {
                                                $last = $a;
                                        }
                                        header("Location: $redir");
                                }
                                break;
                        case 'down':
                                $res = q("select {$this->keyfield},{$this->ordering} from {$this->table} where {$this->where} order by {$this->ordering}");
                                while ($a=fa($res)) {
                                        if ($a[$this->keyfield] == $id) {
                                                //Save this one, and grab the next one
                                                $current = $a;
                                                $a=fa($res);
                                                if ($a) {
                                                        //echo "Current: ".$current[$this->keyfield] ." , ".$current[$this->ordering];
                                                        //echo "<br>Next: ".$a[$this->keyfield] ." , ".$a[$this->ordering];
                                                        if ($current[$this->ordering] == $a[$this->ordering]) {//bump them all up by 1
                                                                q("update {$this->table} set {$this->ordering}={$this->ordering}+1 where {$this->where} and {$this->ordering} >= ".$a[$this->ordering]);
                                                                q("update {$this->table} set {$this->ordering}=".$a[$this->ordering]." where {$this->where} and {$this->keyfield} = ".$a[$this->keyfield]);
                                                        } else {
                                                                q("update {$this->table} set {$this->ordering}=".$current[$this->ordering]." where {$this->where} and {$this->keyfield} = ".$a[$this->keyfield]);
                                                                q("update {$this->table} set {$this->ordering}=".$a[$this->ordering]." where {$this->where} and {$this->keyfield} = ".$current[$this->keyfield]);
                                                        }
                                                } //else it's last row

                                        }
                                        header("Location: $redir");
                                }
                                break;
                        default:
                                die("unknown move");
                                break;
                }
        }
        function mceedit(){

                $page=$_GET['mce'];
                $_SESSION['mcetable'] = $page;

                if (strstr($page,'.') || strstr($page,'/') || strstr($page,"\\") || strstr($page,'%') || strstr($page,'`') || strstr($page,'"') || strstr($page,"'"))
                        die("hijacking columns are we?");
                $res = q("select $page from $this->table where $this->keyfield = $_SESSION[sqlid] and $this->where");
                $a = fa($res);
                $content = $a[$page];
                $html = <<<EOF
                <!-- tinyMCE -->
<script language="javascript" type="text/javascript" src="lib/mce/tiny_mce.js"></script>
<script language="javascript" type="text/javascript">
        tinyMCE.init({
                mode : "textareas",
                theme : "advanced",
                plugins : "spellchecker,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,insertdatetime,preview,media,searchreplace,print,contextmenu,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,imagemanager,filemanager,ibrowser",
                theme_advanced_buttons1_add_before : "save,newdocument,separator",
                theme_advanced_buttons1_add : "fontselect,fontsizeselect",
                theme_advanced_buttons2_add : "separator,insertdate,inserttime,preview,separator,forecolor,backcolor",
                theme_advanced_buttons2_add_before: "cut,copy,paste,pastetext,pasteword,separator,search,replace,separator",
                theme_advanced_buttons3_add_before : "tablecontrols,separator",
                theme_advanced_buttons3_add : "emotions,iespell,media,advhr,separator,print,separator,ltr,rtl,separator,fullscreen",
                theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,spellchecker,cite,abbr,acronym,del,ins,|,visualchars,nonbreaking,ibrowser",
                theme_advanced_toolbar_location : "top",
                theme_advanced_toolbar_align : "left",
                theme_advanced_statusbar_location : "bottom",
                content_css : "css/mce_word.css",
            plugin_insertdate_dateFormat : "%Y-%m-%d",
            plugin_insertdate_timeFormat : "%H:%M:%S",
                extended_valid_elements : "img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]",
                paste_auto_cleanup_on_paste : true,
                paste_convert_headers_to_strong : true
        });

</script>
<!-- /tinyMCE -->

<form method=post>
<textarea name=mcetable rows=30>$a[$page]</textarea><br>
<input type=submit value="Save">
</form>
EOF;
/*              external_link_list_url : "example_data/example_link_list.js",
                external_image_list_url : "example_data/example_image_list.js",
                flash_external_list_url : "example_data/example_flash_list.js",*/

                return $html;
        }

        function mcesave() {
                if (!isset($_SESSION['mcetable'])) {
                        die("missing post data");
                }
                $column = $_SESSION['mcetable'];
                $cleandata = str_replace("\\'","'",$_POST['mcetable']);
                $cleandata = str_replace("'","\'",$cleandata);
                q("UPDATE $this->table set $column='$cleandata' where $this->where and $this->keyfield = $_SESSION[sqlid]");
                if ($this->timestamp) {
                        q("UPDATE $this->table set $this->timestamp=now() where $this->where and $this->keyfield = $_SESSION[sqlid]");
                }
                $uri = str_replace("&mce=contents","",$_SERVER['REQUEST_URI']);
                header("Location: $uri");
                return;
        }
        function gettablecols($table){
                $res = mysql_query("SHOW FIELDS FROM $table") or die(mysql_error());
                while ($row = mysql_fetch_array($res)) {
                        $return[$row['Field']] = $row['Type'];
                }
                return $return;
        }

}
