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

    <xsl:output method="xml" indent="yes"
                encoding="UTF-8"
            />
    <xsl:strip-space elements="*"/>

    <xsl:template match="/">
    doc = {
       <xsl:apply-templates />
    }

    </xsl:template>

    <!-- document properties .e.g title -->
    <xsl:template match="head">
       properties : {
        <xsl:apply-templates/>
        },
    </xsl:template>
    <xsl:template match="head/*"><xsl:value-of select="local-name()"/>: '<xsl:value-of select="."/>',</xsl:template>

    <xsl:template match="body">
        items : [
        <xsl:apply-templates/>
        ],
    </xsl:template>
    <xsl:template match="toc">
        {
          type : 'toc',
          items : [
        <xsl:apply-templates/>
        ]
        },
    </xsl:template>
    <xsl:template match="item">
        <xsl:variable name="valueOfContent"><xsl:value-of select="./content"/></xsl:variable> <!-- it slowing -->
        <xsl:choose>
            <xsl:when test="$valueOfContent = ''"></xsl:when>
            <xsl:otherwise>
        {
          type : '<xsl:value-of select="@type"/>',
          style : '<xsl:value-of select="@style"/>',
          content : '<xsl:apply-templates/>'
        },
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <!-- <xsl:variable name="style">Footnote</xsl:variable> -->

    <xsl:template match="footnoteReference">
        <xsl:variable name="refId" select="@refId"/>
        {{<xsl:value-of select="/xml/body/item[@id=$refId][@style='Footnote']"/>}}
    </xsl:template>

    <xsl:template match="item[@style='Footnote']">
    </xsl:template>
    <xsl:template match="item[@style='Footer']">
    </xsl:template>
    <xsl:template match="item[@style='']">
    </xsl:template>
    <!-- <xsl:template match="item[@content='']">
    </xsl:template> -->

    <xsl:template match="f:bold"><b><xsl:apply-templates/></b></xsl:template>
    <xsl:template match="f:italic"><i><xsl:apply-templates/></i></xsl:template>
    <!-- <xsl:template match="f:strikethrough">&lt;f:s&gt;<xsl:apply-templates/>&lt;/f:s&gt;</xsl:template> -->
    <!-- <xsl:template match="f:line">&lt;f:l&gt;<xsl:apply-templates/>&lt;/f:l&gt;</xsl:template> -->

    <xsl:template match="text()"><xsl:value-of select="translate(.,'�','')"/></xsl:template>

</xsl:stylesheet>
