<refentry id="{@id}">
 <refnamediv>
  <refname>The TIP_Form module</refname>
 </refnamediv>
 <refsynopsisdiv>
  <author>
   Nicola Fontana
   <authorblurb>{@link mailto:ntd@users.sourceforge.net ntd@users.sourceforge.net}</authorblurb>
  </author>
 </refsynopsisdiv>
 <refsect1 id="{@id general}">
  <title>General informations</title>
  <para>This module provides a generic way to build forms for the TIP system. It is heavely based on the HTML_QuickForm PEAR package.</para>
  <para>The building of a form is usually done by calling the TIP_Block::addForm() or TIP_Block::editForm() methods. These functions will instantiate a TIP_Form object, calling the properly sequence of methods to build a common form in the page content.</para>
  <refsect2 id="{@id overview}">
   <title>How the form is built</title>
   <para>The form management is processed by separated steps which are fulfilled by the following methods:</para>
   <itemizedlist>
    <listitem>
     <para><emphasis>setForm()</emphasis></para>
     <para>Configuration of the form module: this method MUST be called before any other to set the fundamental parameters of the TIP_Form module.</para>
    </listitem>
    <listitem>
     <para><emphasis>make()</emphasis></para>
     <para>This is the main function of the form management: it creates a form for the block by inspecting the field structure of its $data object.</para>
     <para>The structure is obtained from the $data object of the binded block by calling TIP_Data::getFields() and passing true as $detailed argument.</para>
    </listitem>
    <listitem>
     <para><emphasis>process()</emphasis></para>
     <para>Processing a form means executing all the tasks the form is born for: validating, storing the result and freezing the form. Only the render process is left over to the TIP_Form::render() method. This is done to allow the rendering of the form when you need it, usually in a callback of the application.</para>
     <para>If the validation fails, the render process will add a submit button and will output the form not frozen (that is ready for input), showing the failed validation using the HTML_QuickForm methods.</para>
     <para>If the validation is succesful, the storing of the data will be done by calling to the TIP_Form::$on_process callback and the TIP_Form::view() method will be called.</para>
    </listitem>
    <listitem>
     <para><emphasis>view()</emphasis></para>
     <para>Simply adds a 'Close' button and freezes the form. The link the 'Close' button must point to is defined with the $referer argument: if not specified, the referer of the server ($_SERVER['HTTP_REFERER']) will be used instead.</para>
    </listitem>
    <listitem>
     <para><emphasis>render()</emphasis></para>
     <para>The HTML representation of the form in the current state (frozen or not) is output.</para>
    </listitem>
   </itemizedlist>
   <para>To build a form to allow to add or edit a row in a specified block, you must use a function similar to the following (TIP_Block::addForm() and TIP_Block::editForm() use the private function TIP_Block::_editRow(), which is similar):</para>
   <para>
    <programlisting role="php">
     <![CDATA[
     function addEditForm(&$block, $defaults, $is_add)
     {
         // Instantiate a singleton of TIP_Form
         $form =& TIP_Module::getInstance('form');

         // Set the fundamental options of the form
         // $defaults is an associative array of default values (usually is used a data row)
         // $is_add is the mode flag (true = add form, false = edit form)
         $form->setForm($block, $defaults, $is_add);

         // Now the form is created by $form->make() and processed by $form->process().
         // If anyone of these return false, an error occurred.
         if (!$form->make() || !$form->process()) {
             // Error handling
             ...
             return false;
         }

         // Rendering process
         return $this->render();
     }
     ]]>
    </programlisting>
   </para>
   <para>The view() method is provided separately to allow to view a frozen row content. This is done by constructing the form in a similar way of what shown above and using the view() method instead of process().</para>
  </refsect2>
 </refsect1>
 <refsect1 id="{@id technical}">
 <title>Technical informations</title>
  <refsect2 id="{@id structure}">
   <title>Field keys used</title>
   <para>As cited above, the form is built by checking the field structure of a TIP_Data object.</para>
   <para>
    <itemizedlist>
     <listitem>
      <para><emphasis>'widget'</emphasis> => 'text' | 'password' | 'date' | 'time' | 'datetime' | 'enum' | 'set' | 'textarea' | 'file' | null</para>
      <para>The type of widget to construct. If null, this value defaults to 'text'.</para>
      <para>The set widget is a list of boolean options and will be rendered as a series of checkboxes.</para>
      <para>The enum widget is a single choice between options. If the choices are more than 3, the widget will be rendered as a select group, otherwise will be a series of optionboxes.</para>
     </listitem>
     <listitem>
      <para><emphasis>'choices'</emphasis> => comma separated list of text</para>
      <para>A list of choices for enum or set widgets. If left undefined, and the widget is a set type, the field will be rendered with a checkbox without label. This is common for boolean fields.</para>
     </listitem>
     <listitem>
      <para><emphasis>'category'</emphasis> => 'required' | 'suggested' | 'optional'</para>
      <para>The importance of this field. This is used to set the required constraint (if category is 'required', obviously) and, by some renderers, to visually group the fields in three different category.</para>
     </listitem>
     <listitem>
      <para><emphasis>'order'</emphasis> => integer</para>
      <para>A number to allow specifying a different sorting model. The fields with 'order' undefined will be the last ones rendered. Keep in mind that some renderers can use the category as higher priority sorting clause.</para>
     </listitem>
     <listitem>
      <para><emphasis>'rules'</emphasis> => rule1[(format)],rule2[(format)],...</para>
      <para>This contains a comma separated list of rules to be satisfied to validate the field content. The format is optional and must be omitted for rules that don't need any format parameter. For instance, in 'email,maxlength(20),required' the email and required rules don't have any format parameter.</para>
      <para>For rules that need more than one parameters, such as rangelength and mimetype, you must separate the formats with a space, as in 'rangelength(10 20)'. This has the disadvantage that you cannot embed spaces in format (nor commas, because used to separate the rules): take this in mind while using regular expression based rules.</para>
      <para>Some rules, such as 'numeric' and 'required', are guessed by the TIP system and there is no need to add them manually. Others rules, such as 'maxlength' and 'date', are automatically set only on specific widget types.</para>
      <para>Any valid HTML_QuickForm rule is allowed.</para>
      <para>
       <itemizedlist>
        <listitem><para><emphasis>required</emphasis>: field required. Automatically set by TIP if the field is in the 'required' category.</para></listitem>
        <listitem><para><emphasis>maxlength(max_length)</emphasis>: maximum field length. Automatically set by TIP for 'text' and 'password' widgets to the $field['length'] content.</para></listitem>
        <listitem><para><emphasis>minlength(min_length)</emphasis>: minimum field length.</para></listitem>
        <listitem><para><emphasis>rangelength(min_length max_length)</emphasis>: check the field length is between min_length and max_length.</para></listitem>
        <listitem><para><emphasis>regex(regular_expression)</emphasis>: check the field content against the specified regular expression.</para></listitem>
        <listitem><para><emphasis>email</emphasis>: check if the field is a valid email expression.</para></listitem>
        <listitem><para><emphasis>unique(field_name)</emphasis>: the inserted value must be unique in the column 'field_name' of the form table. The only exception is if the found row has the same id of the row edited in the form.</para></listitem>
        <listitem><para><emphasis>lettersonly</emphasis>: the field must contain only letters.</para></listitem>
        <listitem><para><emphasis>alphanumeric</emphasis>: the field must contain only letters and numbers.</para></listitem>
        <listitem><para><emphasis>numeric</emphasis>: the field must be a number. Automatically set by TIP on fields if is_numeric($field['default']) is true.</para></listitem>
        <listitem><para><emphasis>nopunctuation</emphasis>: the field must not contain punctuation character.</para></listitem>
        <listitem><para><emphasis>nonzero</emphasis>: the field must be a number not starting with 0.</para></listitem>
        <listitem><para><emphasis>uploadedfile</emphasis>: required file upload.</para></listitem>
        <listitem><para><emphasis>maxfilesize(max_size)</emphasis>: the uploaded file size must not exceed the given number of bytes maximum file size.</para></listitem>
        <listitem><para><emphasis>mimetype(mime1 mime2 ...)</emphasis>: a space separated list of allowed mime types in file uploads.</para></listitem>
        <listitem><para><emphasis>filename(regular_expression)</emphasis>: the file name of the file to upload is checked against the specified regular expression.</para></listitem>
        <listitem><para><emphasis>minimagesize(min_width min_height)</emphasis>: the uploaded image size (as returned by getimagesize) must be greather than min_width and min_height.</para></listitem>
        <listitem><para><emphasis>maximagesize(max_width max_height)</emphasis>: the uploaded image size (as returned by getimagesize) must be less than max_width and max_height.</para></listitem>
       </itemizedlist>
      </para>
     </listitem>
     <listitem>
      <para><emphasis>'wiki_rules'</emphasis>: a comma separated list of filters to use while rendering 'textarea' widgets. If not specified, by default all the rules will be applied.</para>
      <para>
       <itemizedlist>
        <listitem><para><emphasis>Heading</emphasis>: allow header elements (+ first header, ++ second header, ...)</para></listitem>
        <listitem><para><emphasis>Toc</emphasis>: generate a table of contents based on the headers ([[toc]])</para></listitem>
        <listitem><para><emphasis>Horiz</emphasis>: draw an horizontal rule (----)</para></listitem>
        <listitem><para><emphasis>Blockquote</emphasis>: enable the use of citations (> text to cite)</para></listitem>
        <listitem><para><emphasis>List</emphasis>: enable numbered and bullet lists (# numbered element, * bullet list)</para></listitem>
        <listitem><para><emphasis>Deflist</emphasis>: enable definition lists (: item1 : description)</para></listitem>
        <listitem><para><emphasis>Table</emphasis>: allow the rendering of tables (|| first cell || second cell ... ||)</para></listitem>
        <listitem><para><emphasis>Center</emphasis>: allow to center paragraphs (= centered text)</para></listitem>
        <listitem><para><emphasis>Url</emphasis>: enable the use of inline URLs (http://www.bresciapoint.local)</para></listitem>
        <listitem><para><emphasis>Strong</emphasis>: allow strong text (** strong text **)</para></listitem>
        <listitem><para><emphasis>Emphasis</emphasis>: allow emphasis text (// emphasis text //)</para></listitem>
        <listitem><para><emphasis>Revise</emphasis>: enable revised feature (@@--- deleted text@@ @@+++ inserted text@@)</para></listitem>
       </itemizedlist>
      </para>
     </listitem>
    </itemizedlist>
   </para>
  </refsect2>
 </refsect1>
</refentry>
