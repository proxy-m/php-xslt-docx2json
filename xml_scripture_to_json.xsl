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
                xmlns:f="urn:docx2json:intermediary"
                exclude-result-prefixes="w r rels a wp cp dc dcterms f">

    <xsl:output method="xml" indent="no" omit-xml-declaration="yes" encoding="UTF-8" />
    <xsl:strip-space elements="*"/>

    <xsl:template match="/">{<xsl:apply-templates/>}</xsl:template>

    <!-- document properties .e.g title -->
    <xsl:template match="head"><xsl:apply-templates/></xsl:template>
    <xsl:template match="head/*">
        <xsl:variable name="headerParamName"><xsl:value-of select="local-name()"/></xsl:variable>
        <xsl:if test="$headerParamName = 'sourceDocxFileName'">"file": "<xsl:value-of select="."/>",</xsl:if>
    </xsl:template>


    <xsl:template match="data">
"data" : [<xsl:apply-templates/>]
</xsl:template>

    <xsl:template match="item">
            <!-- <xsl:choose> -->
                <!-- <xsl:when test="$valueOfContentZ='00' or $valueOfContentZ='000' or $valueOfContentZ='0000' or $valueOfContentZ='00000' or $valueOfContentZ='000000' or $valueOfContentZ='0000000'"> -->
        <xsl:variable name="valueOfDate" select="./day"/>
        <xsl:choose>
                    <!-- <xsl:variable name="valueOfDate"><xsl:value-of select="$valueOfContent"/> -->
            <xsl:when test="$valueOfDate='000'"></xsl:when>
            <xsl:otherwise>
<xsl:variable name="valueOfDateZ" select="normalize-space(substring-before(concat($valueOfDate, ','), ','))"/><!-- date without day of week name -->
<xsl:variable name="dayOfWeek" select="normalize-space(substring-after($valueOfDate, ','))"/><!-- Day of Week -->
<xsl:variable name="valueOfDateDR" select="substring-before(concat($valueOfDateZ, ' '), ' ')"/><!-- Day Raw -->
<xsl:variable name="valueOfDateMN" select="substring-after($valueOfDateZ, ' ')"/><!-- Month Name -->
<xsl:variable name="valueOfDateMNU" select="translate($valueOfDateMN, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ')"/><!-- Month Name to Upper case -->
<xsl:variable name="valueOfDateDS" select="substring-before(concat($valueOfDateDR, '('), '(')"/><!-- Day Short -->
<xsl:variable name="valueOfDateDD" select="format-number($valueOfDateDS, '#00')"/><!-- standard 2-digit day format -->
<xsl:variable name="valueOfDateMSH"><!-- Month SHorh name --><xsl:choose>
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
    {"day" : "<xsl:value-of select="concat($sourceCalendarYear, '-', $valueOfDateMM, '-', $valueOfDateDD)"/>"<!--, "dayOfWeek": "<xsl:value-of select="$dayOfWeek"/>"-->, "scripture" : "<xsl:apply-templates select='./scripture'/>"}<xsl:if test="position() != last()">,</xsl:if></xsl:otherwise>
    <!-- </xsl:when> -->
<!-- <xsl:otherwise><xsl:value-of select="translate($valueOfContent, '&quot;', '&#x26;apm;')"/></xsl:otherwise></xsl:choose></xsl:otherwise> -->
        </xsl:choose>
    </xsl:template>

    <!-- <xsl:template match="f:strikethrough">&lt;f:s&gt;<xsl:apply-templates/>&lt;/f:s&gt;</xsl:template> -->
    <!-- <xsl:template match="f:line">&lt;f:l&gt;<xsl:apply-templates/>&lt;/f:l&gt;</xsl:template> -->

    <!-- <xsl:template match="text()"><xsl:value-of select="translate(.,'�','')"/></xsl:template> -->
    <!-- <xsl:template match="text()"><zzzz/> -->
        <!-- <xsl:value-of select="translate(.,'&#x0d;&#x0a;', '')"/> -->
    <!-- </xsl:template> -->
    <!-- <xsl:template match="f:bold"><xsl:variable name="f_bold"><xsl:apply-templates/></xsl:variable><xsl:if test="$f_bold != ''"><xsl:value-of select="translate(concat('&lt;b&gt;',$f_bold, '&lt;/b&gt;'),'&#x0d;&#x0a;', '')"/></xsl:if></xsl:template> -->
    <!-- <xsl:template match="f:italic"><xsl:variable name="f_italic"><xsl:apply-templates/></xsl:variable><xsl:if test="$f_italic != ''"><xsl:value-of select="translate(concat('&lt;i&gt;',$f_italic, '&lt;/i&gt;'),'&#x0d;&#x0a;', '')"/></xsl:if></xsl:template> -->

    <!-- <xsl:value-of select="translate(.,'&#x0d;&#x0a;', '')" /> -->

</xsl:stylesheet>
