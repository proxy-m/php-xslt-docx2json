<?xml version="1.0" encoding="UTF-8" standalone="yes"?>

<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
                xmlns:rels="http://schemas.openxmlformats.org/package/2006/relationships"
                xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
                xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
                xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:dcterms="http://purl.org/dc/terms/"
                xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
                xmlns:f="urn:docx2json:intermediary"
                exclude-result-prefixes="w r rels a wp cp dc dcterms mc f">

    <xsl:output method="xml" indent="no" encoding="UTF-8" />
    <xsl:strip-space elements="*"/>

    <xsl:template match="/"><doc><xsl:apply-templates/></doc></xsl:template>

    <!-- document properties .e.g title -->
    <xsl:template match="head"><head><xsl:apply-templates/></head></xsl:template>
    <xsl:template match="head/*">
        <xsl:variable name="headerParamName"><xsl:value-of select="local-name()"/></xsl:variable>
        <xsl:variable name="headerParamValue"><xsl:value-of select="."/></xsl:variable>
        <!-- <xsl:if test="$headerParamName = 'sourceDocxFileName' or $headerParamName = 'sourceXmlFileName'"> -->
        <!--<xsl:value-of select="string(concat('&lt;param key="', $headerParamName, '" value="', $headerParamValue, '"&gt;&lt;/param&gt;'))"/>-->
        <!-- <xsl:value-of select="concat('&lt;param key=', $headerParamName,  ' value=', $headerParamValue, '/param', 'dfae')"/> -->
        <xsl:value-of select="concat('&lt;', $headerParamName,  '&gt;', $headerParamValue, '&lt;/', $headerParamName,  '&gt;')"/>
        <!-- </xsl:if> -->
    </xsl:template>

    <xsl:template match="body">
<data>
<xsl:variable name="valueOfDate000">000</xsl:variable>
    <item>
        <day><xsl:value-of select="$valueOfDate000"/></day><scripture><xsl:variable name="itemsList"><xsl:apply-templates/></xsl:variable><xsl:value-of select="$itemsList"/></scripture>
    </item>    
</data>
</xsl:template>
    <!-- <xsl:template match="body">"items" : [<xsl:apply-templates/>],</xsl:template> -->
    <xsl:template match="toc">
        <toc>
            <xsl:apply-templates/>
        </toc>
    </xsl:template>

    <xsl:template match="item">
        <xsl:variable name="valueOfContent"><xsl:apply-templates select="./content"/></xsl:variable> <!-- it slowing -->
        <xsl:choose>
            <xsl:when test="$valueOfContent = ''"></xsl:when>
            <!-- <xsl:when test="$valueOfContent = ''"></xsl:when> -->
            <xsl:otherwise>

                <xsl:variable name="valueOfContent0" select="translate($valueOfContent, '()123456789', '00000000000')"/>
                <xsl:variable name="valueOfContentS" select="substring-before(concat($valueOfContent0, ','), ',')"/>
                <xsl:variable name="valueOfContentZ" select="substring-before(concat($valueOfContentS, ' '), ' ')"/>
                
                <xsl:choose>
                    <xsl:when test="$valueOfContentZ='00' or $valueOfContentZ='000' or $valueOfContentZ='0000' or $valueOfContentZ='00000' or $valueOfContentZ='000000' or $valueOfContentZ='0000000' or $valueOfContentZ='00000000'">
<xsl:variable name="valueOfDate"><xsl:value-of disable-output-escaping="yes" select="$valueOfContent"/></xsl:variable>&#x3c;/scripture&#x3e;
    &lt;/item&gt;
    &lt;item&gt;
        &#x3c;day&#x3e;<xsl:value-of select="$valueOfDate"/>&#x3c;/day&#x3e;&#x3c;scripture&#x3e;</xsl:when>
                    <xsl:otherwise><xsl:value-of disable-output-escaping="yes" select="translate($valueOfContent, '&quot;', '&#x26;apm;')"/></xsl:otherwise>
                </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <!-- <xsl:variable name="style">Footnote</xsl:variable> -->

    <xsl:template match="footnoteReference"><xsl:variable name="refId" select="@refId"/>{{<xsl:value-of select="/xml/body/item[@id=$refId][@style='Footnote']"/>}}</xsl:template>

    <xsl:template match="item[@style='Footnote']"></xsl:template>
    <xsl:template match="item[@style='Footer']"></xsl:template>
    <xsl:template match="item[@style='']"></xsl:template>
    <!-- <xsl:template match="item[@content='']">
    </xsl:template> -->

    <!-- <xsl:template match="f:strikethrough">&lt;f:s&gt;<xsl:apply-templates/>&lt;/f:s&gt;</xsl:template> -->
    <!-- <xsl:template match="f:line">&lt;f:l&gt;<xsl:apply-templates/>&lt;/f:l&gt;</xsl:template> -->

    <!-- <xsl:template match="text()"><xsl:value-of select="translate(.,'�','')"/></xsl:template> -->
    
    <xsl:template match="text()"><xsl:value-of disable-output-escaping="yes" select="translate(.,'&#x0d;&#x0a;', '')"/></xsl:template>
    <xsl:template match="f:bold"><xsl:variable name="f_bold"><xsl:apply-templates/></xsl:variable><xsl:if test="not($f_bold = '')"><xsl:value-of select="translate(concat('&lt;b&gt;',$f_bold, '&lt;/b&gt;'),'&#x0d;&#x0a;', '')"/></xsl:if></xsl:template>
    <xsl:template match="f:italic"><xsl:variable name="f_italic"><xsl:apply-templates/></xsl:variable><xsl:if test="not($f_italic = '')"><xsl:value-of select="translate(concat('&lt;i&gt;',$f_italic, '&lt;/i&gt;'),'&#x0d;&#x0a;', '')"/></xsl:if></xsl:template>

    <!-- <xsl:value-of select="translate(.,'&#x0d;&#x0a;', '')" /> -->

</xsl:stylesheet>
