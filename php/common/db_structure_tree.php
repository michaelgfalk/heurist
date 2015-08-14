<?php
    /** 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0      
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    /**
    * Generates an array - list and treeview of fields for given recordtypes 
    * used in smarty editor, titlemask editor, facet search wizard
    *
    * dbs_ - prefix for functions
    * 
    * main function: rtt_GetRectypeStructureTree
    * 
    * Internal function
    *  __getRecordTypeTree
    *  __getDetailSection
    */

    $dbs_rtStructs = null;
    $dbs_lookups = null; //human readale field type names

    /**
    * Returns  
    * {
    *   id          : rectype id, field id in form fNNN, name of default rectype field
    *   text        : rectype or field display name
    *   type        : rectype|Relationship|field type|term
    *   children    : []  // array of fields
    * }
    * 
    * @param mixed $system
    * @param mixed $rectypeids
    * @param mixed $mode  3 - all, 4 - for faceted search (with type names)
    */
    function dbs_GetRectypeStructureTree($system, $rectypeids, $mode, $fieldtypes=null){

        global $dbs_rtStructs, $dbs_lookups;

        if($fieldtypes==null){
            $fieldtypes = array('integer','date','freetext','year','float');
        }else if(!is_array($fieldtypes)){
            $fieldtypes = explode(",",$fieldtypes);
        }

        $dbs_rtStructs = dbs_GetRectypeStructures($system, ($mode==4)?null:$rectypeids, 1); 
        $dbs_lookups = dbs_GetDtLookups();

        $rtypes = $dbs_rtStructs['names'];
        $res = array();
        
        
        
        $rectypeids = (!is_array($rectypeids)?explode(",", $rectypeids):$rectypeids);

        //foreach ($rtypes as $rectypeID=>$rectypeName){
        foreach ($rectypeids as $rectypeID){
                $def = __getRecordTypeTree($system, $rectypeID, 0, $mode, $fieldtypes);
                if($def!==null) {
                    //asign codes
                    if(is_array(@$def['children'])){
                        $def = __assignCodes($def);
                        array_push($res, $def);
                    }                    
                }
        }

        return $res;    
    }
    
    
    function __assignCodes($def){
                        foreach($def['children'] as $idx => $det){
                            if(@$def['code']){

                                if(@$det['code']){
                                    $def['children'][$idx]['code'] = $def['code'].":".$det['code']; 
                                }else{
                                    $def['children'][$idx]['code'] = $def['code'];    
                                }
                                
                            }
                                 
//$def['children'][$idx]['title'] = $det['title'].$def['children'][$idx]['code'];                    
//error_log($det['title']);        
                            if(is_array(@$det['children'])){
                                   $def['children'][$idx] = __assignCodes($def['children'][$idx]);
                            }
                        }
                        return $def;
    }

    //
    //   {rt_id: , rt_name, recID, recTitle, recModified, recURL, ecWootText,
    //                  fNNN: 'display name of field', 
    //                  fNNN: array(termfield_name: , id, code:  )  // array of term's subfields
    //                  fNNN: array(rt_name: , recID ...... )       // unconstrained pointer or exact constraint
    //                  fNNN: array(array(rt_id: , rt_name, recID, recTitle ... ) //constrined pointers
    //     NNN - field type ID
    function __getRecordTypeTree($system, $recTypeId, $recursion_depth, $mode, $fieldtypes){

        global $dbs_rtStructs;

        $res = array();
        $children = array();
        //add default fields
        if($mode==3) array_push($children, array('key'=>'recID', 'type'=>'integer', 'title'=>'ID', 'code'=>$recTypeId.":id"));
        array_push($children, array('key'=>'recTitle',    'type'=>'freetext',  'title'=>'RecTitle', 'code'=>$recTypeId.":title"));
        array_push($children, array('key'=>'recModified', 'type'=>'date',      'title'=>'Modified', 'code'=>$recTypeId.":modified"));
        if($mode==3) {
            array_push($children, array('key'=>'recURL',      'type'=>'freetext',  'title'=>'URL', 'code'=>$recTypeId.":url"));
            array_push($children, array('key'=>'recWootText', 'type'=>'blocktext', 'title'=>'WootText', 'code'=>$recTypeId.":woot"));
        }

        if($recTypeId && is_numeric($recTypeId)){

            if(!@$dbs_rtStructs['typedefs'][$recTypeId]){
                //this rectype is not loaded yet - load it
                $rt0 = dbs_GetRectypeStructures($system, $recTypeId, 1);
                if($rt0){ //merge with $dbs_rtStructs 
                    $dbs_rtStructs['typedefs'][$recTypeId] = $rt0['typedefs'][$recTypeId];    
                    $dbs_rtStructs['names'][$recTypeId] = $rt0['names'][$recTypeId];
                }else{
                    return null;
                }
            }

            $res['key'] = $recTypeId;
            $res['title'] = $dbs_rtStructs['names'][$recTypeId];
            $res['type'] = 'rectype';

            if(@$dbs_rtStructs['typedefs'][$recTypeId]){
                $details =  $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'];

                foreach ($details as $dtID => $dtValue){
                    
                    $res_dt = __getDetailSection($system, $recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, null);
                    if($res_dt){
                        array_push($children, $res_dt);
                        /*
                        if(is_array($res_dt) && count($res_dt)==1){
                        $res["f".$dtID] = $res_dt[0];    
                        }else{
                        //multi-constrained pointers or simple variable
                        $res["f".$dtID] = $res_dt;
                        }
                        */
                    }
                }//for
                
                //find all reverse links and relations
                if($mode==4){ //&& $recursion_depth==0){
                    $reverse_rectypes = __getReverseLinkedRecordTypes($recTypeId);
                    foreach ($reverse_rectypes as $rtID => $dtID){
                        //$dtValue =  $dbs_rtStructs['typedefs'][$rtID]['dtFields'][$dtID];

                        $res_dt = __getDetailSection($system, $rtID, $dtID, $recursion_depth, $mode, $fieldtypes, $rtID);
                        if($res_dt){
                            array_push($children, $res_dt);
                        }
                    }
                }
            }
            if($mode==3 && $recursion_depth==0){
                array_push($children, __getRecordTypeTree($system, 'Relationship', $recursion_depth+1, $mode, $fieldtypes));
            }   

        }else if($recTypeId=="Relationship") {

            $res['title'] = "Relationship";
            $res['type'] = "relationship";

            //add specific Relationship fields
            array_push($children, array('key'=>'recRelationType', 'title'=>'RelationType'));
            array_push($children, array('key'=>'recRelationNotes', 'title'=>'RelationNotes'));
            array_push($children, array('key'=>'recRelationStartDate', 'title'=>'RelationStartDate'));
            array_push($children, array('key'=>'recRelationEndDate', 'title'=>'RelationEndDate'));
        }

        $res['children'] = $children;

        return $res;
    }
    
    //
    // returns array of record types that are linked to giveb record type
    //
    function __getReverseLinkedRecordTypes($rt_ID){
        
        global $dbs_rtStructs;
        
        
        //find all reverse links (pointers and relation that point to selected rt_ID)
        $alldetails = $dbs_rtStructs['typedefs'];
        $fi_type = $alldetails['dtFieldNamesToIndex']['dty_Type'];
        $fi_rectypes = $alldetails['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        
        $arr_rectypes = array();
        
        foreach ($alldetails as $recTypeId => $details){
        
            if(is_numeric($recTypeId) && $recTypeId!=$rt_ID){
         
//error_log($recTypeId); //print_r($dbs_rtStructs, true)
//continue;
                
                $details = $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'];
                
                foreach ($details as $dtID => $dtValue){
                
                        if($dtValue[$fi_type]=='resource' || $dtValue[$fi_type]=='relmarker'){

                                //find constraints
                                $constraints = $dtValue[$fi_rectypes];
                                $constraints = explode(",", $constraints);
                                //verify that selected record type is in this constaint
                                if(count($constraints)>0 && in_array($rt_ID, $constraints) && !@$arr_rectypes[$recTypeId] ){
                                    
                                    //error_log(">>>".in_array($rt_ID, $constraints)."<<  ".$rt_ID."  ".print_r($constraints, true));
                                    
                                    $arr_rectypes[$recTypeId] = $dtID;
                                }
                        }
                }
            }
        }
        
//        if(count($arr_rectypes)>0) error_log("rev for ".$rt_ID."  ".print_r($arr_rectypes, true));
        
        return  $arr_rectypes;
        
    }

    /*
    $dtID   - detail type ID

    $dtValue - record type structure definition

    returns display name  or if enum array

    $mode - 3 all, 4 for facet treeview
    */
    function __getDetailSection($system, $recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, $reverseRecTypeId){

        global $dbs_rtStructs, $dbs_lookups;    

        $res = null;

        $rtNames = $dbs_rtStructs['names']; //???need
        $rst_fi = $dbs_rtStructs['typedefs']['dtFieldNamesToIndex'];

        $dtValue = $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'][$dtID];

        $detailType = $dtValue[$rst_fi['dty_Type']];
        $dt_label   = $dtValue[$rst_fi['rst_DisplayName']];
        $dt_title   = $dtValue[$rst_fi['rst_DisplayName']];
        $dt_tooltip = $dtValue[$rst_fi['rst_DisplayHelpText']]; //help text

        $pref = "";
        //$dt_maxvalues = $dtValue[$rst_fi['rst_MaxValues']]; //repeatable
        //$issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)==1)?"true":"false";
        //error_log("1>>>".$mode."  ".$detailType."  ".$dt_label);

        switch ($detailType) {
            /* @TODO
            case 'file':
            break;
            case 'geo':
            break;
            case 'calculated':
            break;
            case 'fieldsetmarker':
            break;
            case 'relationtype':
            */
            case 'separator':
                return null;
            case 'enum':

                $res = array();
                if($mode==3){
                    $res['children'] = array(
                        array("text"=>"internalid"),
                        array("text"=>"code"),
                        array("text"=>"term"),
                        array("text"=>"conceptid"));
                }
                break;

            case 'resource': // link to another record type
            case 'relmarker':

                if($recursion_depth<2){



                    
                    if($reverseRecTypeId!=null){
                            $res = __getRecordTypeTree($system, $reverseRecTypeId, $recursion_depth+1, $mode, $fieldtypes);
                            if($res){
                                $res['rt_ids'] = "".$reverseRecTypeId; //list of rectype - constraint
                                //$res['reverse'] = "yes";
                                $pref = ($detailType=="resource")?"lf":"rf";
                                $dt_title = " <span style='font-style:italic'>" . $rtNames[$reverseRecTypeId] ."  ". $dt_title . "</span>";
                            }
                    }else{

                            $pref = ($detailType=="resource")?"lt":"rt";
                             
                            if($mode==4){
                                $dt_title = " <span style='font-style:italic'>" . $dt_title . "</span>";
                            }
                            
                            $pointerRecTypeId = $dtValue[$rst_fi['rst_PtrFilteredIDs']];
                            $rectype_ids = explode(",", $pointerRecTypeId);

                            if($pointerRecTypeId=="" || count($rectype_ids)==0){ //unconstrainded

                                $res = __getRecordTypeTree($system, null, $recursion_depth+1, $mode, $fieldtypes);

                            }else{ //constrained pointer

                                $res = array();


                                if(count($rectype_ids)>1){
                                    $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    $res['children'] = array();
                                }

                                foreach($rectype_ids as $rtID){
                                    $rt_res = __getRecordTypeTree($system, $rtID, $recursion_depth+1, $mode, $fieldtypes);
                                    if(count($rectype_ids)==1){//exact one rectype constraint
                                        //avoid redundant level in tree
                                        $res = $rt_res;
                                        $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    }else if($rt_res!=null){
                                        array_push($res['children'], $rt_res);
                                    }
                                }
                            
                            }

                    }
                }

                break;

            default:
                //error_log("2>>>".$mode."  ".$detailType."  ".$dt_label."   ".($detailType=='float'));            
                if (($mode==3) ||  in_array($detailType, $fieldtypes))
                {
                    //error_log("!!!!!!!!!");                    
                    $res = array();
                }
        }//end switch

        //error_log("3>>>>".is_array($res)."<  ".$detailType."  ".$dt_label);
        if(is_array($res)){

            if(!@$res['code']) $res['code'] = $recTypeId.":".$pref.$dtID;
            $res['key'] = "f:".$dtID;
            if($mode==4){
                $res['title'] = $dt_title . " <span style='font-size:0.7em'>(" 
                    . $dbs_lookups[$detailType] 
                    . (($reverseRecTypeId!=null)?" [reverse]":"" ) .")</span>";   
                //$res['code'] = 
            }else{
                $res['title'] = $dt_title;    
            }
            $res['type'] = $detailType;
            $res['name'] = $dt_label;
            
        }            
        return $res;
    }

