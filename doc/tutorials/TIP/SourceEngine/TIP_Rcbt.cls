<refentry id="{@id}">
 <refnamediv>
  <refname>The Rcbt source engine</refname>
 </refnamediv>
 <refsect1 id="{@id description}">
  <title>Description</title>
  <para>This is the first implementation of the TIP_SourceEngine class. The format of an rcbt file is quite simple: it is a pure HTML file that can contains some tags: these tags are simple text enclosed by curly braces.</para>
  <caution>The curly braces must be used <emphasis>only</emphasis> to enclose tags. For performance reasons, the Rcbt implementation does not provide any way to escape curly braces!</caution>
  <refsect2 id="{@id syntax}">
   <title>Tag sintax</title>
   <para>An RCBT tag has the following syntax:</para>
   <programlisting>{[module.]command([params])}</programlisting>
   <itemizedlist>
    <listitem>
     <para><emphasis>module</emphasis> (case insensitive)</para>
     <para>Identifies the module to use while executing this tag. If not specified, the caller module will be used.</para>
    </listitem>
    <listitem>
     <para><emphasis>command</emphasis> (case insensitive)</para>
     <para>Defines the comman to call. The available commands are module dependents: consult the module documentation to see which commands are available, particulary the TIP_Module::callCommand() function. As mentioned above, if not specified the 'html' command will be executed.</para>
    </listitem>
    <listitem>
     <para><emphasis>params</emphasis> (case sensitive)</para>
     <para>The arguments to pass to the command. Obviously, what params means depend by the command called.</para>
     <para>The content of params will be recursively executed before the call: this means the parameters can contains itsself any sort of valid tags.</para>
    </listitem>
   </itemizedlist>
   <para>The square brakets identify an optional part. All the following tags are valid tags:</para>
   <itemizedlist>
    <listitem><para><emphasis>{Blog.run(block.html)}</emphasis>: a complete tag</para></listitem>
    <listitem><para><emphasis>{User.logout()}</emphasis>: a tag without parameters</para></listitem>
    <listitem><para><emphasis>{Html(age)}</emphasis>: a tag without module</para></listitem>
    <listitem><para><emphasis>{logout()}</emphasis>: a tag without module and parameters</para></listitem>
    <listitem><para><emphasis>{age}</emphasis>: this is a special case and will expand to <emphasis>{html(age)}</emphasis></para></listitem>
    <listitem><para><emphasis>{(age)}</emphasis>: another special case that will expand to <emphasis>{tryHtml(age)}</emphasis></para></listitem>
    <listitem><para><emphasis>{}</emphasis>: the empty tag is used to close the context opened by some built-in commands, such as forSelect() of if()</para></listitem>
   </itemizedlist>
  </refsect2>
 </refsect1>
 <refsect1 id="{@id interaction}">
  <title>How does it work?</title>
  <para>All the source content that not is a tag (or between a context enclosed by special tags) it is passed-throught, that is it goes to the output without modifications.</para>
  <para>When a tag is found in the source file, it is parsed in its component (module, command and params) and it is executed. This means the engine calls the function <emphasis>module->callCommand(command, params)</emphasis> using the parsed module, command and params. See TIP_Module::callCommand() for details on how this function works.</para>
  <refsect2 id="{@id builtins}">
   <title>Built-in commands</title>
   <para>Some command is built-in, that is when the engine found one of such command the engine does not call the callCommand() method, but it executes a special operation. The following list shows these built-ins commands (the [module.] part of the tags is omitted for clarity):</para>
   <itemizedlist>
    <listitem>
     <para><emphasis>{if([conditions])}</emphasis></para>
     <para>Executes the text enclosed by this tag and the <emphasis>{}</emphasis> tag only if conditions is true. During the execution of this text, the default module will be the current module.</para>
    </listitem>
    <listitem>
     <para><emphasis>{else()}</emphasis></para>
     <para>Switches the state of the skip flag. The skip flag tells the engine to output or not the parsed source.</para>
    </listitem>
    <listitem>
     <para><emphasis>{select([filter])}</emphasis></para>
     <para>Performs the specified select on the data of the module and runs the text enclosed by this tag and the <emphasis>{}</emphasis> tag with this select active. This can be helpful, for instance, to show the summary fields of a select. During the execution of this text, the default module will be the module specified by the tag. This means if you do not specify the select, this tag simply change the default module.</para>
    </listitem>
    <listitem>
     <para><emphasis>{selectRow([id])}</emphasis></para>
     <para>A shortcut to the select command performing a select on the primary key content.</para>
    </listitem>
    <listitem>
     <para><emphasis>{forSelect([filter])}</emphasis></para>
     <para>Performs the specified filter on the module and, for every row, runs the text enclosed by this tag and the <emphasis>{}</emphasis> tag. During the execution of this text, the current module will be the tag module.</para>
    </listitem>
    <listitem>
     <para><emphasis>{forEach([name])}</emphasis></para>
     <para>If you do not specify name, it is the same as forSelect but traverses the current view instead of building a new one.</para>
     <para>If name is a number, the enclosed text is executed name times; while parsing the enclosed text, the special item 'CNT' keeps track of the current time (that is 'CNT' is the time counter).</para>
     <para>In the other cases, a special view is created by calling TIP_Block::startSpecialView(name). A common value of name is 'FIELDS', that generates a special view that browses the field structure. Take a look to the documentation of TIP_Block::startSpecialView() for further informations.</para>
    </listitem>
   </itemizedlist>
  </refsect2>
 </refsect1>
</refentry>
