<?php
function xbbcode_menu($maycache) {
        if ($maycache) return;
        $items=array();
		$items[]=array(
                'path'=>'admin/settings/xbbcode',
                'type'=>MENU_NORMAL_ITEM,
                'title'=>t('XBBCode Tags'),
                'callback'=>'xbbcode_handlers',
		);
	    $items[]=array(
                'path'=>'admin/settings/xbbcode/handlers',
                'type'=>MENU_DEFAULT_LOCAL_TASK,
                'title'=>t('Tag Settings'),
        );
		$items[]=array(
                'path'=>'admin/settings/xbbcode/handlers/-1',
                'type'=>MENU_DEFAULT_LOCAL_TASK,
                'title'=>t('Global'),
				'weight'=>-1,
			);
		foreach (XBBCode::list_formats() as $key=>$format) {
		  $items[] = array(
			'path' => 'admin/settings/xbbcode/handlers/'. $key,
			'title' => t('!key', array('!key' => $format)),
			'callback' => 'xbbcode_handlers',
			'callback arguments' => array($key,$format),
			'access' => user_access('administer blocks'),
			'type'=>MENU_LOCAL_TASK,
		  );
		}	
        $items[]=array(
                'path'=>'admin/settings/xbbcode/tags',
                'type'=>MENU_LOCAL_TASK,
                'title'=>t('Custom Tags'),
				'weight'=>1,
                'callback'=>'xbbcode_custom_tags',
        );

        return $items;
}

function xbbcode_filter($op,$delta=0,$format='-1',$text='') {
		global $xbbcode_formats;
		$xbbcode_formats[$format]=$format;
        //var_dump(func_get_args());
		global $xbbcode_filters;
        switch($op) {
                case 'list': return array('Extensible BBCode');
                case 'description': return t('Allows custom BBCode tags');
                case 'no cache': return true;
                case 'process': 
					if (!$xbbcode_filters[$format]) $xbbcode_filters[$format]=XBBCode::filter_from_format($format);
					return $xbbcode_filters[$format]->process($text);
                case 'settings': return 
					array(
						array(
							'#type'=>'item',
							'#value'=>t("You may adjust the !link for this format.",
								array('!link'=>l(t("tag settings"),'admin/settings/xbbcode/handlers/'.$format))),
						)
					);
        }
        return $text;
}

function xbbcode_filter_tips($delta=0,$format=-1,$long=false) {
	global $xbbcode_filters;
	if (!$xbbcode_filters[$format]) $xbbcode_filters[$format]=XBBCode::filter_from_format($format);
	//var_dump($xbbcode_filters);
	if (!$xbbcode_filters[$format]->tags) return; // no tags, no tips.
	$out.=t('You may use these tags: ');
	if ($long) {
		$out.='<ul>';
		foreach ($xbbcode_filters[$format]->tags as $name=>$tag)
		{
		  //var_dump($tag);
		  if (!$tag['sample'])
		  { 
		    if (!$tag['selfclosing']) $tag['sample'] = "[$name]content[/$name]";
		    else $tag['sample']="[$name]";
		  }
		  $out.='<li><strong>'.str_replace("\n","<br />",check_plain(t($tag['sample']))).'</strong><br />';
		  $out.=check_plain(t($tag['description'])).'<br />';
		  $out.='<div style="display:block;padding:10px;">';
		  $out.=xbbcode_filter('process',$delta,$format,t($tag['sample']));
		  $out.='</div>';
		  $out.='</li>';
		}
		$out.='</ul>';
	} else {
		$out.="[".implode("], [",array_keys($xbbcode_filters[$format]->tags))."]";
	}
	return $out;
}

function xbbcode_install($table=NULL) {
	if (!$table || $table=='tags') {
		$sql = '
	CREATE TABLE 
		{xbbcode_custom_tags}
		(
			name VARCHAR(32),
			replacewith TEXT NOT NULL,
			description TEXT NOT NULL,
			sample TEXT NOT NULL,
			dynamic BOOLEAN NOT NULL DEFAULT false,
			selfclosing BOOLEAN NOT NULL DEFAULT false,
			multiarg BOOLEAN NOT NULL DEFAULT false,
			PRIMARY KEY (name)
		);';
		$ret[]=db_query($sql);
	}
	if (!$table || $table=='handlers') {
		$sql = '
		CREATE TABLE 
			{xbbcode_handlers}
			(
				name VARCHAR(32),
				format INT(4) NOT NULL DEFAULT -1,
				module VARCHAR(32),
				weight INT(2) NOT NULL DEFAULT 0,
				enabled BOOLEAN NOT NULL DEFAULT TRUE,
				PRIMARY KEY (name,format)
			);';
		$ret[]=db_query($sql);
		// And insert all tag handlers into it to allow resolving conflicts per format.
		$tags=XBBCode::get_module_tags();
		foreach ($tags as $tag) {
			$module=$tag['module'];
			//if (!$module) $module='xbbcode'; // user-created tags are "handled" by this module.
			//unnecessary; they are now in fact handled by this module.
			if (!db_result(db_query("SELECT COUNT(*) FROM {xbbcode_handlers} WHERE name='%s'",$tag['name'])))
			{	// only add it if it doesn't exist yet. assigns defaults by first come first served.
				db_query("INSERT INTO {xbbcode_handlers} (name,module) VALUES('%s','%s')",$tag['name'],$tag['module']);
			}
		}
	}
	return $ret;
}

// From version 0.1.1 to version 0.1.2:

function xbbcode_update_1() {
	// Add table xbbcode_handlers
	$ret=xbbcode_install('handlers');
	drupal_set_message('Table {xbbcode_handlers} created.','status');
	return $ret;
}

// From version 0.1.2 to version 0.1.3:
function xbbcode_update_2() {
  // Rename table xbbcode_tags to xbbcode_custom_tags
  update_sql("ALTER TABLE {xbbcode_tags} RENAME {xbbcode_custom_tags};");
}


function xbbcode_enable() {
	if (!db_table_exists("xbbcode_custom_tags")) xbbcode_install();
}

function xbbcode_init() {
	/* initialize tags */
	/*
	global $tags;
	$tags=xbbcode_all_tags();
	*/
}

function xbbcode_help($section) {
	if ($section!='admin/help#modulename') return;
	$out.='<p>How to write the replacement value for a new custom XBBCode tag:</p>';
	$out.='<p>Static text:</p>';
	$out.='<p>Simply enter the HTML code that should replace [tag=option]content[/tag]. The following variables are available to you:
	{option} will be replaced with the value "option" in the above example, {content} with "content". For static replacement,
	only a single option value is available, to use named attributes, use a dynamic tag.</p>';
	$out.='<p>Dynamic text:</p>';
	$out.='<p>Enter the PHP code (without &lt;?php ?&gt;) that should be executed. The following values are available to you:
	If the tag is formed as [tag=option]content[/tag], the same as above.
	If the tag is formed as [tag name=value1 href="value 2" style=\'value 3\']content[/tag],
	 {name}, {href} and {style} will be filled with the appropriate values.</p>';
	$out.='<p>You may return the replacement code with "return", or by assigning it to the variable $out. 
	If no value is returned, $out will be used.</p>';
	return t($out);
}

function xbbcode_xbbcode($op='list',$delta=NULL,$tag=NULL) 
{
	switch($op)
	{
		case 'list':
			global $xbbcode_dynamic_tags;
			$res=db_query("select name from {xbbcode_custom_tags}");
			while ($row=db_fetch_array($res)) $names[]=$row['name'];
			return $names;
		case 'info':
			$tag=db_fetch_array(db_query("select sample,description,selfclosing,dynamic,multiarg,replacewith from {xbbcode_custom_tags} where name='%s'",$delta));
			if ($tag['dynamic']) 
			{
				$xbbcode_dynamic_tags[$tag['name']]=$tag['replacewith'];
				unset($tag['replacewith']);
			}
			return $tag;
		case 'render':
			return xbbcode_xbbcode_render($delta,$tag->args,$tag->content);
	}
}

function xbbcode_xbbcode_render($tag_name,$args,$content)
{
	global $xbbcode_dynamic_tags;
	$code=$xbbcode_dynamic_tags[$tag_name];
	if (is_array($args)) 
	{
		$replace=array_keys($args);
		foreach ($replace as $i=>$name) $replace[$i]='{'.$name.'}';
		$with=array_values($args);
	}
	else
	{
		$replace=array('{option}');
		$with=array($args);
	}
	$replace[]='{content}';
	$with[]=$content;
	$code=str_replace($replace,$with,$code);			
	$code="<?php $code ?>";
	return drupal_eval($code);
}
?>
