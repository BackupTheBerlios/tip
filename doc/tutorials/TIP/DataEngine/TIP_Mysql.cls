<refentry id="{@id}">
 <refnamediv>
  <refname>The Mysql data engine</refname>
 </refnamediv>
 <refsect1 id="{@id interaction}">
  <para>A basic interface to the MySql database.</para>
  <para>The filter parameter, requested by many methods, it is the WHERE and ORDER clauses in the SQL language. The methods filter() and rowFilter() comes in hand to build simple filters.</para>
  <para>Furthermore, the Rcbt engine can access the underline data objects skipping the automatic filter creation function methods. For example, if you want to show the user called 'nicola', you must specify the filter <programlisting>WHERE `user`='nicola'</programlisting>.</para>
  <para>Using the tipRcbt engine, for instance, you could create a source file like the following:</para>
  <para>
   <programlisting role="html">
    <![CDATA[
    <h1>List of the first ten users whose name begins with 'c'</h1>
    {user.forSelect(WHERE `user` LIKE 'c%' LIMIT 10)}
     <p>{user} ({publicname})</p>
    {}
    ]]>
   </programlisting>
  </para>
  <para>In the above example, the forSelect is called from the 'user' module. Also, the forSelect is by definition a read SQL SELECT method. This means the command will expand to the following SQL query:</para>
  <para>
   <programlisting>
    SELECT * FROM `tip_user` WHERE `user` LIKE 'c\%' LIMIT 10
   </programlisting>
  </para>
 </refsect1>
</refentry>
