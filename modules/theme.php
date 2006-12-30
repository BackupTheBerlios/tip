<?php

class tipTheme extends tipModule
{
  // protected:

  var $THEME;


  function SwitchTheme ()
  {
    if (! $this->THEME)
      return FALSE;

    global $cfg, $FIELDS;
    $FIELDS['STYLEDIR'] = "$cfg[STYLEDIR]/$this->THEME";
    $this->ROW['id'] =& $this->THEME;
    return TRUE;
  }


  // public:
  
  function cTheme ()
  {
    $this->cModule ('theme');
    $this->THEME = FALSE;
  }

  function OnActionDone ()
  {
    global $MODULES;
    if (array_key_exists ('user', $MODULES))
      {
	$USER =& $MODULES['user'];
	$USER->AddCommand ('Personalizzazioni', 'theme', 'Browse');

	if ($USER->ID)
	  {
	    if ($this->THEME)
	      $USER->ROW['_theme'] = $this->THEME;
	    else
	      $this->THEME = $USER->ROW['_theme'];
	  }

	$this->SwitchTheme ();
      }
  }


  function Populate ()
  {
    if ($this->ROWS !== FALSE)
      return TRUE;

    global $cfg;
    $BaseDir =& $cfg['STYLEDIR'];

    if (! $DirHandle = opendir ($BaseDir))
      {
	LogMessage ("unable to open `$BaseDir' directory");
        return FALSE;
      }

    while (($Theme = readdir ($DirHandle)) !== FALSE)
      if (substr ($Theme, 0, 1) != '.')
        {
	  $ThemePath = "$BaseDir/$Theme/modules/theme";

	  if (is_dir ($ThemePath))
	    $this->ROWS[] = array
	      (
		'id'          => $Theme,
		'screenshot'  => "$ThemePath/screenshot.jpg",
		'description' => "$ThemePath/description.html"
	      );
	}

    return TRUE;
  }


  // CMS available actions

  function actionBrowse ()
  {
    if ($this->Populate ())
      $this->EchoInContent ('browse.html');
  }

  function actionSet ()
  {
    if (array_key_exists ('id', $_POST))
      {
	$this->THEME = $_POST['id'];
      }
    elseif (array_key_exists ('id', $_GET))
      {
	$this->THEME = $_GET['id'];
      }
    else
      {
	Error ('TH_NOTFOUND');
	return;
      }
  }
}

?>
