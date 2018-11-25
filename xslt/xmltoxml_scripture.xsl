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
                xmlns:date="http://exslt.org/dates-and-times"
                exclude-result-prefixes="w r rels a wp cp dc dcterms mc f">

    <xsl:output method="xml" indent="no" encoding="UTF-8" />
    <xsl:strip-space elements="*"/>

    <xsl:template match="/"><doc>
        <xsl:apply-templates/>
    </doc></xsl:template>

    <!-- document properties .e.g title -->
    <xsl:template match="head">
        <head><now><xsl:value-of select="date:date-time()"/></now>
            <xsl:apply-templates/>
        </head>
    </xsl:template>
    <xsl:template match="head/*">
        <xsl:variable name="headerParamName"><xsl:value-of select="local-name()"/></xsl:variable>
        <xsl:variable name="headerParamValue"><xsl:value-of select="."/></xsl:variable>
        <xsl:value-of select="concat('&lt;', $headerParamName,  '&gt;', $headerParamValue, '&lt;/', $headerParamName,  '&gt;')"/>
        <xsl:apply-templates/>
    </xsl:template>

<xsl:template match="body">
&lt;data&gt;
<xsl:variable name="valueOfDate000">000</xsl:variable>
    &lt;item&gt;
        &lt;dateText&gt;<xsl:value-of select="$valueOfDate000"/>&lt;/dateText&gt;&lt;dateInfo&gt;<xsl:apply-templates />&lt;/dateInfo&gt;
    &lt;/item&gt;
&lt;/data&gt;
</xsl:template>
    <!-- <xsl:template match="body">"items" : [<xsl:apply-templates/>],</xsl:template> -->
    <!-- <xsl:template match="toc">
        <toc>
            <xsl:apply-templates/>
        </toc>
    </xsl:template> -->

    <xsl:template match="item">
        <xsl:variable name="valueOfContent"><xsl:apply-templates select="./content"/></xsl:variable> 
        <!-- it slowing -->
        <xsl:choose>
            <xsl:when test="$valueOfContent = ''"></xsl:when>
            <!-- <xsl:when test="$valueOfContent = ''"></xsl:when> -->
            <xsl:otherwise>

                <xsl:variable name="valueOfContent0" select="translate($valueOfContent, '()123456789', '00000000000')"/>
                <xsl:variable name="valueOfContentS" select="substring-before(concat($valueOfContent0, ','), ',')"/>
                <xsl:variable name="valueOfContentZ" select="substring-before(concat($valueOfContentS, ' '), ' ')"/>
                
                <xsl:choose>
                    <xsl:when test="$valueOfContentZ='00' or $valueOfContentZ='000' or $valueOfContentZ='0000' or $valueOfContentZ='00000' or $valueOfContentZ='000000' or $valueOfContentZ='0000000' or $valueOfContentZ='00000000'">
<xsl:variable name="valueOfDate"><xsl:value-of disable-output-escaping="yes" select="$valueOfContent"/></xsl:variable>&#x3c;/dateInfo&#x3e;

<xsl:variable name="valueOfDateZ" select="normalize-space(substring-before(concat($valueOfDate, ','), ','))"/><!-- date without day of week name -->
<xsl:variable name="dayOfWeek" select="normalize-space(substring-after($valueOfDate, ','))"/><!-- Day of Week -->
<xsl:variable name="valueOfDateDR" select="substring-before(concat($valueOfDateZ, ' '), ' ')"/><!-- Day Raw -->
<xsl:variable name="valueOfDateMN" select="substring-after($valueOfDateZ, ' ')"/><!-- Month Name -->
<xsl:variable name="valueOfDateMNU" select="translate($valueOfDateMN, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ')"/><!-- Month Name to Upper case -->
<xsl:variable name="valueOfDateDS" select="substring-before(concat($valueOfDateDR, '('), '(')"/><!-- Day Short old style (Julian calendar) -->
<xsl:variable name="valueOfDateDD" select="format-number($valueOfDateDS, '#00')"/><!-- standard 2-digit day format -->
<xsl:variable name="valueOfDateMSH"><!-- Month SHort name --><xsl:choose>
    <xsl:when test="$valueOfDateMNU='ЯНВАРЯ'">1</xsl:when>
    <xsl:when test="$valueOfDateMNU='ФЕВРАЛЯ'">2</xsl:when>
    <xsl:when test="$valueOfDateMNU='МАРТА'">3</xsl:when>
    <xsl:when test="$valueOfDateMNU='АПРЕЛЯ'">4</xsl:when>
    <xsl:when test="$valueOfDateMNU='МАЯ'">5</xsl:when>
    <xsl:when test="$valueOfDateMNU='ИЮНЯ'">6</xsl:when>
    <xsl:when test="$valueOfDateMNU='ИЮЛЯ'">7</xsl:when>
    <xsl:when test="$valueOfDateMNU='АВГУСТА'">8</xsl:when>
    <xsl:when test="$valueOfDateMNU='СЕНТЯБРЯ'">9</xsl:when>
    <xsl:when test="$valueOfDateMNU='ОКТЯБРЯ'">10</xsl:when>
    <xsl:when test="$valueOfDateMNU='НОЯБРЯ'">11</xsl:when>
    <xsl:when test="$valueOfDateMNU='ДЕКАБРЯ'">12</xsl:when>
    <xsl:otherwise>0</xsl:otherwise>
</xsl:choose></xsl:variable>
<xsl:variable name="valueOfDateMM" select="format-number($valueOfDateMSH, '#00')"/><!-- standard 2-digit month format -->
<xsl:variable name="dateOld"><xsl:value-of select="date:date(concat($sourceCalendarYear, '-', $valueOfDateMM, '-', $valueOfDateDD))"/></xsl:variable>
<xsl:variable name="date"><xsl:value-of select="date:add($dateOld, 'P13D')"/></xsl:variable>
    &lt;/item&gt;
    &lt;item&gt;
        &#x3c;dateText&#x3e;<xsl:value-of select="$valueOfDateZ"/>&#x3c;/dateText&#x3e;
        &#x3c;dateOld&#x3e;<xsl:value-of select="$dateOld"/>&#x3c;/dateOld&#x3e;
        &#x3c;date&#x3e;<xsl:value-of select="$date"/>&#x3c;/date&#x3e;
        &#x3c;dayOfWeek&#x3e;<xsl:value-of select="$dayOfWeek"/>&#x3c;/dayOfWeek&#x3e;
        &#x3c;dateInfo&#x3e;</xsl:when>
                    <!-- <xsl:otherwise><xsl:value-of disable-output-escaping="yes" select="translate($valueOfContent, '&quot;', '&#x26;apm;')"/></xsl:otherwise> -->
                    <xsl:otherwise><xsl:value-of disable-output-escaping="yes" select="concat($valueOfContent, ' ')"/></xsl:otherwise>
                    <!-- <xsl:otherwise><xsl:apply-templates disable-output-escaping="yes" select="./content"/></xsl:otherwise> -->
                </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <!-- <xsl:variable name="style">Footnote</xsl:variable> -->

    <xsl:template match="footnoteReference"><xsl:variable name="refId" select="@refId"/>{{<xsl:value-of select="/xml/body/item[@id=$refId][@style='Footnote' or @style='Normal']"/>}}</xsl:template>

    <xsl:template match="item[@style='Footnote']"></xsl:template>
    <xsl:template match="item[@style='Normal' and not(@id='')]"></xsl:template>
    <xsl:template match="item[@style='Footer']"></xsl:template>
    <xsl:template match="item[@style='']"></xsl:template>
    <!-- <xsl:template match="item[@content='']">
    </xsl:template> -->

    <xsl:template match="f:strikethrough">&lt;del&gt;<xsl:apply-templates/>&lt;/del&gt;</xsl:template>
    <xsl:template match="del">&lt;del&gt;<xsl:apply-templates/>&lt;/del&gt;</xsl:template>
    <xsl:template match="ins">&lt;ins&gt;<xsl:apply-templates/>&lt;/ins&gt;</xsl:template>
    <xsl:template match="span">&lt;span&gt;<xsl:apply-templates/>&lt;/span&gt;</xsl:template>

    <!-- <xsl:template match="text()"><xsl:value-of select="translate(.,'�','')"/></xsl:template> -->
    <xsl:template match="head/*/text()"></xsl:template>
    <xsl:template match="body/*/text()"></xsl:template>
    <!-- <xsl:template match="text()"><xsl:value-of disable-output-escaping="yes" select="translate(.,'&#x0d;&#x0a;', '')"/></xsl:template> -->
    <xsl:template match="f:bold"><xsl:variable name="f_bold"><xsl:apply-templates/></xsl:variable><xsl:if test="not($f_bold = '')"><xsl:value-of select="translate(concat('&lt;b&gt;',$f_bold, '&lt;/b&gt;'),'&#x0d;&#x0a;', '')"/></xsl:if></xsl:template>
    <xsl:template match="f:italic"><xsl:variable name="f_italic"><xsl:apply-templates/></xsl:variable><xsl:if test="not($f_italic = '')"><xsl:value-of select="translate(concat('&lt;i&gt;',$f_italic, '&lt;/i&gt;'),'&#x0d;&#x0a;', '')"/></xsl:if></xsl:template>

    <!-- <xsl:value-of select="translate(.,'&#x0d;&#x0a;', '')" /> -->

</xsl:stylesheet>
