<?php
$GVARS['rep_is_online']  = TRUE;
require_once("include/bittorrent.php");
require_once "include/user_functions.php";
dbconn(false);
if (!logged_in()) {
    header("HTTP/1.0 404 Not Found");
    // moddifed logginorreturn by retro//Remember to change the following line to match your server
    print("<html><h1>Not Found</h1><p>The requested URL /{$_SERVER['PHP_SELF']} was not found on this server.</p><hr /><address>Apache/1.1.11 ".$SITENAME." Server at " . $_SERVER['SERVER_NAME'] . " Port 80</address></body></html>\n");
    die();
}


define( 'TIMENOW', time() ) ;

// mod or not?
$is_mod = ( $CURUSER['class'] >= UC_MODERATOR ) ? TRUE : FALSE;

//$CURUSER['class'] = 2;
//$rep_maxperday = 10;
//$rep_repeat = 20;
$closewindow = TRUE;
require_once "cache/rep_settings_cache.php";
//print_r($GVARS);

		if( ! $GVARS['rep_is_online']  )
		{
			exit( 'Reputation system offline, sorry!' );
		}


///////////////////////////////////////////////
//	Need only deal with one input value
///////////////////////////////////////////////

		if( isset( $_POST ) || isset( $_GET ) )
		{
		$input = array_merge( $_GET, $_POST );
		//print_r($input);
		//die;
		}

//$input['reputation'] = 'pos';
//$input['reason'] = 'la di da di di la';
///////////////////////////////////////////////		
//	Just added to Reputation?
///////////////////////////////////////////////
		if( isset( $input['done'] ) )
		{
			rep_output( 'Reputation Added!' );
		}

///////////////////////////////////////////////
//	Nope, so do something different, like check stuff
///////////////////////////////////////////////       
/// weeeeeeeeee =]
$check = isset($input['pid']) ? is_valid_id($input['pid']) : false;

$locales = array('posts', 'comments', 'torrents', 'users');
$rep_locale = (isset($input['locale']) && (in_array($input['locale'], $locales)) ?$input['locale'] : 'posts');


if (!$check)
{
    rep_output('Incorrect Access');
}

if ($rep_locale == 'posts')
{
    ///////////////////////////////////////////////
    // check the post actually exists!
    ///////////////////////////////////////////////
    $forum = mysql_query("SELECT posts.topicid AS locale, posts.userid, forums.minclassread,
users.username, users.reputation
FROM posts
LEFT JOIN topics ON topicid = topics.id
LEFT JOIN forums ON topics.forumid = forums.id
LEFT JOIN users ON posts.userid = users.id
WHERE posts.id ={$input['pid']}");
}

elseif ($rep_locale == 'comments')
{
    ///////////////////////////////////////////////
    // check the comment actually exists!
    ///////////////////////////////////////////////
      //uncomment the following  if use comments.anonymous field
      $forum = mysql_query("SELECT comments.id, comments.user AS userid, comments.anonymous AS anon,
      comments.torrent AS locale,
      users.username, users.reputation
      FROM comments
      LEFT JOIN users ON comments.user = users.id
      WHERE comments.id = {$input['pid']}");
     
}

elseif ($rep_locale == 'torrents')
{
    ///////////////////////////////////////////////
    // check the uploader actually exists!
    ///////////////////////////////////////////////

    //uncomment the following  if use torrents.anonymous field
     $forum = mysql_query("SELECT torrents.id as locale, torrents.owner AS userid, torrents.anonymous AS anon,
     users.username, users.reputation
     FROM torrents
     LEFT JOIN users ON torrents.owner = users.id
     WHERE torrents.id ={$input['pid']}");
     
}
elseif ($rep_locale == 'users')
{
    ///////////////////////////////////////////////
    // check the user actually exists!
    ///////////////////////////////////////////////
    $forum = mysql_query("SELECT id AS userid, username, reputation
FROM users
WHERE id ={$input['pid']}");
} // end

switch ($rep_locale)
{
    case 'comments':
        $this_rep = 'Comment';
        break;
    case 'torrents':
        $this_rep = 'Torrent';
        break;
    case 'users':
        $this_rep = 'Profile';
        break;
    default:
        $this_rep = 'Post';
}

// does it or don't it?
if (!mysql_num_rows($forum))
    rep_output($this_rep.' Does Not Exist - Incorrect Access');

///////////////////////////////////////////////
// ok, lets proceed
///////////////////////////////////////////////
$res = mysql_fetch_assoc($forum) or sqlerr(__line__, __file__);

if (isset($res['minclassread'])) // 'posts'
    if ($CURUSER['class'] < $res['minclassread'])
        // check permissions! Dun want sneaky pests lookin!

    {
        rep_output('Wrong Permissions');
    }
///////////////////////////////////////////////
//	Does the user have memory loss? Have they already rep'd?
///////////////////////////////////////////////
		$repeat = mysql_query( "SELECT postid FROM reputation WHERE postid ={$input['pid']} 
						AND whoadded={$CURUSER['id']}" );

		//$repres = mysql_fetch_assoc( $forum ) or sqlerr(__LINE__,__FILE__);
		

		if( mysql_num_rows( $repeat) > 0 ) // blOOdy eedjit check!
		{
			rep_output( 'You have already added Rep to this entry!' ); // Is insane!
		}
		
///////////////////////////////////////////////
// 	Is a mod or gone over the limit?
///////////////////////////////////////////////
		if( ! $is_mod )
		{
			if( $GVARS['rep_maxperday'] >= $GVARS['rep_repeat'] )
			{
				$klimit = intval($GVARS['rep_maxperday'] + 1);
			}
			else
			{
				$klimit = intval($GVARS['rep_repeat'] + 1);
			}

///////////////////////////////////////////////
//	Some trivial flood checking
///////////////////////////////////////////////
			$flood = mysql_query( "SELECT dateadd, userid FROM reputation 
									WHERE whoadded = {$CURUSER['id']} 
									ORDER BY dateadd DESC
									LIMIT 0 , $klimit" );
			

			if( mysql_num_rows( $flood ) )
			{
				$i = 0;
				while( $check = mysql_fetch_assoc( $flood ) )
				{
					if( ( $i < $GVARS['rep_repeat'] ) && ( $check['userid'] == $CURUSER['id'] ) )//$res['userid'] ) )
					{
						rep_output( 'You cannot rep your own stuffs!' );
					}
					if( ( ( $i + 1 ) == $GVARS['rep_maxperday'] ) && ( ( $check['dateadd'] + 86400 ) > TIMENOW ) )
					{
						rep_output( 'The game is up, you rep spammer!' );
					}
					$i++;
				}
			}
		}

///////////////////////////////////////////////
//	Passed flood checkin, what to do now?
///////////////////////////////////////////////
		// Note: if you use another forum type, you may already have this GLOBAL available
		// So you can save a query here, else...
		$r = mysql_query("SELECT COUNT(*) FROM posts WHERE userid = {$CURUSER['id']}") or sqlerr();
		$a = mysql_fetch_row($r) or sqlerr();
		$CURUSER['posts'] = $a[0];
///////////////////////////////////////////////
// What's the reason for bothering me?
///////////////////////////////////////////////
		$reason = '';
		
		if( isset( $input['reason'] ) && !empty( $input['reason'] ) )
		{
			
			$reason = trim($input['reason']);

			$temp = stripslashes( $input['reason'] );
			if( ( strlen(trim($temp)) < 2 ) || ( $reason == "" ) )
			{
				rep_output( 'Reason is too short!' );
			}

			if( strlen( preg_replace("/&#([0-9]+);/", "-", stripslashes( $input['reason'] ) ) ) > 250 )
			{
				rep_output( 'Reputation reason too long!' );
			}
		}
		
//$input['do'] = 'addrep';
//$input['reputation'] = 1;
///////////////////////////////////////////////
//	Are we adding a rep or what?
///////////////////////////////////////////////
		if( isset( $input['do'] ) && $input['do']  == 'addrep' )
		{
			if( $res['userid'] == $CURUSER['id'] ) // sneaky bastiges!
			{
				rep_output( 'You cannot rep your own post!' );
			}

			$score = fetch_reppower( $CURUSER, $input['reputation'] );
			$res['reputation'] += $score;

			@mysql_query( "UPDATE users set reputation=".intval($res['reputation']). " WHERE id=" .$res['userid'] );

			$save = array( 'reputation' => $score,
                           'whoadded'   => $CURUSER['id'],
                           'reason'     => sqlesc($reason),
                           'dateadd'   => TIMENOW,
                           'locale' => sqlesc($rep_locale),
                           'postid'     => (int)$input['pid'],
                           'userid'     => $res['userid']
                        );

			//print( join( ',', $save) );
			//print( join(',', array_keys($save)));
			@mysql_query( "INSERT INTO reputation (".join(',', array_keys($save)).") VALUES (".join( ',', $save).")" );

			header( "Location: $BASEURL/reputation.php?pid={$input['pid']}&done=1" );
		} // Move along, nothing to see here!
		else
		{
			if( $res['userid'] == $CURUSER['id'] ) // same as him!
			{
				// check for fish!
			  $query = mysql_query( "select r.*, leftby.id as leftby_id, leftby.username as leftby_name
                                        from reputation r
                                        left join users leftby on leftby.id=r.whoadded
                                        where postid={$input['pid']}
                                        AND r.locale = ".sqlesc($input['locale'])."
                                        order by dateadd DESC" );
										
				$reasonbits = '';
				
				if( false !== mysql_num_rows($query) )
				{
					$total = 0;
					
					while( $postrep = mysql_fetch_assoc($query) )
					{
						$total += $postrep['reputation'];

						if( $postrep['reputation'] > 0 )
						{
							$posneg = 'pos';
						}
						elseif( $postrep['reputation'] < 0 )
						{
							$posneg = 'neg';
						}
						else
						{
							$posneg = 'balance';
						}

						if( $GVARS['g_rep_seeown'] )
						{
							$postrep['reason'] = $postrep['reason']." <span class='desc'>Leftby <a href=\"$BASEURL/userdetails.php?id={$postrep['leftby_id']}\" target='_blank'>{$postrep['leftby_name']}</a></span>";
						}

						$reasonbits .= "<tr>
	<td class='row2' width='1%'><img src='./pic/rep/reputation_$posneg.gif' border='0' alt='' /></td>
	<td class='row2'>{$postrep['reason']}</td>
</tr>";
					}
///////////////////////////////////////////////
//	The negativity...oh such negativity
///////////////////////////////////////////////
					if( $total == 0 ){ $rep = 'Even'; }
					elseif( $total > 0 && $total <= 5 ){ $rep = 'Somewhat Positive'; }
					elseif( $total > 5 && $total <= 15 ){ $rep = 'Positive'; }
					elseif( $total > 15 && $total <= 25 ){ $rep = 'Very Positive'; }
					elseif( $total > 25 ){ $rep = 'Extremely Positive'; }
					elseif( $total < 0 && $total >= -5 ){ $rep = 'Somewhat Negative'; }
					elseif( $total < -5 && $total >= -15 ){ $rep = 'Negative'; }
					elseif( $total < -15 && $total >= -25){ $rep = 'Very Negative'; }
					elseif( $total < -25 ){ $rep = 'Extremely Negative'; }
				}
				else
				{
					$rep = 'Even'; //Ok, dunno what to do, so just make it quits!
				}
				switch ($rep_locale)
    {
        case 'comments':
            $rep_info = sprintf("Your reputation on <a href='".$BASEURL."/details.php?id=%d&amp;viewcomm=%d#comm%d' target='_blank'>this Comment</a> is %s<br/ >Total: %s points.", $res['locale'], $input['pid'], $input['pid'], $rep, $total);
            break;
        case 'torrents':
            $rep_info = sprintf("Your reputation on <a href='".$BASEURL."/details.php?id=%d' target='_blank'>this Torrent</a> is %s<br/ >Total: %s points.", $input['pid'], $rep, $total);
            break;
        case 'users':
            $rep_info = sprintf("Your reputation on <a href='".$BASEURL."/userdetails.php?id=%d' target='_blank'>your profile</a> is %s<br/ >Total: %s points.", $input['pid'], $rep, $total);
            break;
         default:
            $rep_info = sprintf("Your reputation on <a href='".$BASEURL."/forums.php?action=viewtopic&amp;topicid=%d&amp;page=p%d#%d' target='_blank'>this Post</a> is %s<br/ >Total: %s points.", $res['locale'], $input['pid'], $input['pid'], $rep, $total);
}
///////////////////////////////////////////////
//	Compile some HTML for the 'own post'/ 'user view' reputation
//	Feel free to do ya own html/css here
///////////////////////////////////////////////
				//$rep_info = sprintf("Your reputation on <a href='$BASEURL/forums.php?action=viewtopic&amp;topicid=%d&amp;page=p%d#%d' target='_blank'>this post</a> is %s.", $res['topicid'], $input['pid'], $input['pid'], $rep );
				$rep_points = sprintf("You have %d Reputation point(s).", $CURUSER['reputation'] );

				$html = "<tr><td class='darkrow1'>{$rep_info}</td></tr>
						<tr>
							<td class='row2'>
							<div class='tablepad'>";

					if ( $reasonbits )
					{
					
					$html .= "<fieldset class='fieldset'>
								<legend>Reputation Comments</legend>
								<table class='ipbtable' cellpadding='0'>
								$reasonbits
								</table>
							</fieldset><br />";
					
					}

						$html .=	"<div class='formsubtitle' align='center'><strong>{$rep_points}</strong></div>
						</div>
						</td>
					</tr>";
			}
			else
			{
				///////////////////////////////////////////////
				//	HTML/CSS for 'add reputaion'
				//	Feel free to alter HTML/CSS here
				///////////////////////////////////////////////
				$res['anon'] = (isset($res['anon'])?$res['anon']:'no');
        $rep_text = sprintf("What do you think of %s's ".$this_rep."?", ($res['anon'] == 'yes'?'Anonymous':$res['username']));
        $negativerep = ($is_mod || $GVARS['g_rep_negative'] ) ? TRUE : FALSE;
				$closewindow = FALSE;

				$html = "<tr><td class='darkrow1'>Add to reputation: <b>{$res['username']}</b></td></tr>
						<tr>
							<td class='row2'>
							<form action='reputation.php' method='post'>	
							<div class='tablepad'>
								<fieldset>
									<legend>$rep_text</legend>
									<table class='f_row' cellspacing='0'>
									<tr>
										<td>
											<div><label for='rb_reputation_pos'>
											<input type='radio' name='reputation' value='pos' id='rb_reputation_pos' checked='checked' class='radiobutton' style='margin:0px;' /> &nbsp;I Approve</label></div>";
					if ( $negativerep )
					{
					$html .= "<div><label for='rb_reputation_neg'><input type='radio' name='reputation' value='neg' id='rb_reputation_neg' class='radiobutton' style='margin:0px;' /> &nbsp;I Disapprove</label></div>";
					}
				$html .= "</td>
							</tr>
							<tr>
								<td>
									Your comments on this ".$this_rep.":<br />
									<input type='text' size='40' maxlength='250' name='reason' style='margin:0px;' />
								</td>
							</tr>
							</table>
						</fieldset>
					</div>
					<div align='center' style='margin-top:3px;'>
						<input type='hidden' name='act' value='reputation' />
						<input type='hidden' name='do' value='addrep' />
						<input type='hidden' name='pid' value='{$input['pid']}' />
						<input type='hidden' name='locale' value='{$input['locale']}' />
						<input type='submit' value='Add To Reputation' class='button' accesskey='s' />
						<input type='button' value='Close Window' class='button' accesskey='c' onclick='self.close()' />
					</div>	
					</form>	
					</td>
				</tr>";
			}

			rep_output( "", $html ); // send to spewer-outer function
		} // END
	

///////////////////////////////////////////////
//	Reputation output function
//	$msg -> string
//	$html -> string 
///////////////////////////////////////////////

	function rep_output($msg="", $html="")
	{
		global $closewindow;
		
		if( $msg && empty($html) )
		{
			$html = "<tr><td class='row2'>$msg</td></tr>";
		}

		$html = "<body style='background-color: lightblue;'><div class='borderwrap'>
	<div class='maintitle'>Reputation System</div>
	<table class='main' cellspacing='1'>
		$html";
		if ( $closewindow )
		{
		$html .= "<tr><td class='darkrow1' align='center'><a href='javascript:self.close();'><b>Close Rep</b></a></td></tr>";
		}
		$html .= "</table></div></body>";
		print $html;
		exit();
		
	}

///////////////////////////////////////////////
//	Fetch Reputation function
//	$user -> array all about the user
//	$rep -> string what kind of rep this user has
///////////////////////////////////////////////

function fetch_reppower($user=array(),$rep='pos')
	{
		global $GVARS, $is_mod;
		// is the user allowed to do negative reps?
		if( ! $GVARS['g_rep_negative'] )
		{
			$rep = 'pos';
		}

		if( ! $GVARS['g_rep_use'] ) // allowed to rep at all?
		{
			$rep = 0;
		}
		elseif( $is_mod && $GVARS['rep_adminpower'] ) // is a mod and has loadsa power?
		{ //work out positive or negative admin power
			$reppower = ( $rep != 'pos' ) ? intval($GVARS['rep_adminpower'] * -1) : intval($GVARS['rep_adminpower']);
		}
		elseif( ( $user['posts'] < $GVARS['rep_minpost'] ) || ( $user['reputation'] < $GVARS['rep_minrep'] ) )
		{ // not an admin, then work out postal based power
			$reppower = 0;
		}
		else
		{ // ok failed all tests, so ratio is 1:1 but not negative, unless allowed
			$reppower = 1;

			if( $GVARS['rep_pcpower'] )
			{ // percentage power
				$reppower += intval($user['posts'] / $GVARS['rep_pcpower']);
			}

			if( $GVARS['rep_kppower'] )
			{ // rep as based upon a constant of kppower global
				$reppower += intval($user['reputation'] / $GVARS['rep_kppower']);
			}

			if( $GVARS['rep_rdpower'] )
			{ // time based power
				$reppower += intval((TIMENOW - strtotime( $user['added'] )) / 86400 / $GVARS['rep_rdpower']);
			}

			if( $rep != 'pos' )
			{
				// Negative rep is worth half that of positive, but must be atleast 1, else it gets messy
				$reppower = intval($reppower / 2);
				$reppower = ( $reppower < 1 ) ? 1 : $reppower;
				$reppower *= -1;
			}
		}

		return $reppower;
	}

// erm, FIN
?>