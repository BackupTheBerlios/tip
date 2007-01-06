<?php

/**
 * Base class for source engines.
 *
 * Provides a common interface to access any source file.
 **/
class tipSource extends tipType
{
  /// @protectedsection

  /**
   * Parses and executes a buffer.
   * @param[in] Buffer \c string    The buffer to run.
   * @param[in] Module \c tipModule The caller module.
   *
   * Parses and executes the commands specified in \p Buffer. Because of
   * \p Buffer can be a huge chunck of memory, it is passed by reference to
   * improve performances and avoid undesired copy overload.
   *
   * This method MUST be overriden by all the types that inherits tipSource.
   *
   * @return \c TRUE on success or a string containing an error message.
   **/
  function RealRun (&$Buffer, &$Module, $PreMessage)
  {
    $this->LogFatal ('method tipSource::RealRun() not implemented');
  }


  /// @publicsection

  /**
   * Parses and executes a file.
   * @param[in] File   \c string    The file to run
   * @param[in] Module \c tipModule The caller module
   *
   * Parses and executes the specified file.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function Run ($File, &$Module)
  {
    if (empty ($File))
      {
	$this->LogError ('file not specified');
	return FALSE;
      }

    $Source = file_get_contents ($File, 0);
    if (! $Source)
      {
	$this->LogError ("file '$File' not found");
	return FALSE;
      }

    return $this->RealRun ($Source, $Module, "Source '$File'");
  }

  /**
   * Parses and executes a file.
   * @param[in]  File   \c string    The file to run
   * @param[in]  Module \c tipModule The caller module
   * @param[out] Buffer \c string    The output buffer
   *
   * Similar to Run(), but redirects the output to buffer.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function RunTo ($File, &$Module, &$Buffer)
  {
    ob_start ();
    $Result = $this->Run ($File, $Module);
    $Buffer = ob_get_clean ();

    return $Result;
  }
}

?>
