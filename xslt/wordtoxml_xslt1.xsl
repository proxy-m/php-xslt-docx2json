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
                exclude-result-prefixes="w r rels a wp cp dc dcterms mc">

    <xsl:output method="xml"
                indent="no"
                encoding="UTF-8"/>
    <xsl:strip-space elements="*"/>

    <xsl:template match="/">
        <xml>
            <head>
                <xsl:apply-templates select="w:document/cp:coreProperties"/>
                <sourceXmlFileName><xsl:value-of select="$sourceXmlFileName"/></sourceXmlFileName>
                <sourceDocxFileName><xsl:value-of select="$sourceDocxFileName"/></sourceDocxFileName>
                <sourceCalendarYear><xsl:value-of select="$sourceCalendarYear"/></sourceCalendarYear>
            </head>
            <body>
                <xsl:apply-templates />
            </body>
        </xml>
    </xsl:template>

    <!-- document data e.g. title and description -->
    <xsl:template match="dc:* | dcterms:*">
        <xsl:element name="{local-name()}">
            <xsl:value-of select="."/>
        </xsl:element>
    </xsl:template>

    <!-- 'alternate content' is skipped -->
    <xsl:template match="mc:AlternateContent" mode="ol">

    </xsl:template>

    <!-- TOC -->
    <xsl:template match="w:p[w:pPr/w:pStyle/@w:val[starts-with(., 'ContentsHeading')]]" mode="toc">
        <toc>
            <heading><xsl:value-of select="."/></heading>
            <links>
               <xsl:apply-templates select="//w:p" mode="toc_section"/>
            </links>
        </toc>
    </xsl:template>
    <xsl:template match="w:p[w:pPr/w:pStyle/@w:val[starts-with(., 'Contents1')]]" mode="toc_section">
       <xsl:variable name="link_text">
           <xsl:call-template name="link_text">
               <xsl:with-param name="link" select="w:hyperlink"/>
           </xsl:call-template>
       </xsl:variable>
       <link name="{$link_text}"
             target="{w:hyperlink/@w:anchor}"
             style="{w:pPr/w:pStyle/@w:val}">
       </link>
    </xsl:template>
    <xsl:template match="w:t" mode="toc_section"/>

    <!-- Alternate TOC spec -->
    <xsl:template match="w:sdt" priority="1"/>
    <xsl:template match="w:sdt[w:sdtContent/w:p/w:hyperlink]" priority="2">
        <toc>
            <xsl:apply-templates/>
        </toc>
    </xsl:template>
    <xsl:template match="w:sdtContent">
        <heading>
            <xsl:value-of select="w:p[w:pPr/w:pStyle[@w:val='ContentsHeading']]/w:r/w:t"/>
            <xsl:value-of select="w:p[w:pPr/w:pStyle[@w:val='TOCHeading']]/w:r/w:t"/>
        </heading>
        <links>
            <xsl:for-each select="w:p[w:hyperlink]">
                <xsl:variable name="link_text">
                    <xsl:call-template name="link_text">
                        <xsl:with-param name="link" select="w:hyperlink"/>
                    </xsl:call-template>
                </xsl:variable>
                <link name="{$link_text}"
                      target="{w:hyperlink/@w:anchor}"
                      style="{w:pPr/w:pStyle/@w:val}">
                </link>
            </xsl:for-each>
        </links>
    </xsl:template>
    <xsl:template name="link_text">
        <xsl:param name="link"/>
        <xsl:value-of select="$link/w:r/w:t"/>
        <!--
        <xsl:for-each select="$link/w:r/w:t">
            <xsl:value-of select="."/> &#160;-
        </xsl:for-each>
        -->
    </xsl:template>

    <!-- footnote Reference -->
    <xsl:template match="w:footnoteReference">
        <footnoteReference refId="{@w:id}" />
    </xsl:template>

    <!-- Basic Content -->
    <xsl:template match="w:p">
        <xsl:variable name="style0"><xsl:value-of select="w:pPr/w:pStyle/@w:val"/></xsl:variable>
        <xsl:variable name="style"><xsl:choose>
            <xsl:when test="w:pPr[w:pStyle/@w:val]"><xsl:value-of select="$style0"/></xsl:when>
            <xsl:otherwise>Normal</xsl:otherwise>
        </xsl:choose></xsl:variable>
        <xsl:choose>
            <!--<xsl:when test="w:pPr/w:widowControl"></xsl:when>-->
            <xsl:when test="w:pPr[w:pStyle/@w:val[ starts-with( ., 'ContentsHeading' ) ] ]">
                <xsl:apply-templates select="self::*" mode="toc"/>
            </xsl:when>
            <xsl:when test="w:pPr[w:pStyle/@w:val[ starts-with( ., 'Contents1' ) ] ]"/>
            <xsl:when test="w:pPr[w:pStyle/@w:val[ starts-with( ., 'Heading' ) ] ]">
                <item
                        type="heading"
                        style="{$style}"
                        size="{/w:document/w:styles/w:style[@w:styleId=$style]/w:rPr/w:sz/@w:val}"
                >
                    <content>
                        <xsl:apply-templates/>
                    </content>
                </item>
            </xsl:when>
            <!-- <xsl:when test="w:pPr/w:numPr">
                <xsl:apply-templates select="self::*" mode="ol"/>
            </xsl:when> -->
            <xsl:when test="w:r/w:drawing">
                <item
                        type="image"
                        style="{$style}"
                        size="{/w:document/w:styles/w:style[@w:styleId=$style]/w:rPr/w:sz/@w:val}">
                    <content>
                        <xsl:apply-templates/>
                    </content>
                </item>
            </xsl:when>
            <xsl:otherwise>
                <item
                        type="section"
                        id="{../@w:id}"
                        style="{$style}"
                        size="{/w:document/w:styles/w:style[@w:styleId=$style]/w:rPr/w:sz/@w:val}">
                    <content>
                        <xsl:apply-templates/>
                    </content>
                </item>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Text content -->
    <xsl:template match="w:r">
        <xsl:choose>
            <xsl:when test="w:rPr/w:b[not(@w:val) or @w:val='true']"><f:bold><xsl:apply-templates/></f:bold></xsl:when>
            <xsl:when test="w:rPr/w:i[not(@w:val) or @w:val='true']"><f:italic><xsl:apply-templates/></f:italic></xsl:when>
            <xsl:when test="w:rPr/w:highlight"><span style="background-color:{w:rPr/w:highlight/@w:val}"><xsl:apply-templates/></span></xsl:when>
            <xsl:when test="w:rPr/w:strike[not(@w:val) or @w:val='true']"><f:strikethrough><xsl:apply-templates/></f:strikethrough></xsl:when>
            <xsl:when test="w:rPr/w:ins[not(@w:val) or @w:val='true']"><ins><xsl:apply-templates/></ins></xsl:when>
            <xsl:when test="w:rPr/w:del[not(@w:val) or @w:val='true']"><del><xsl:apply-templates/></del></xsl:when>
            <xsl:otherwise><xsl:apply-templates/></xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template match="w:br">
        <f:linebreak />
    </xsl:template>

    <!-- <xsl:template match="w:t"><xsl:value-of select="translate(.,'�Â','')"/></xsl:template> -->
    <xsl:template match="w:t"><xsl:value-of select="translate(.,'&#x0d;&#x0a;', '')"/></xsl:template>
    <!-- <xsl:template match="w:t"><xsl:value-of select="."/></xsl:template> -->

    <!-- images -->
    <xsl:template match="w:drawing">
        <xsl:apply-templates select=".//a:blip"/>
    </xsl:template>
    <xsl:template match="a:blip">
        <xsl:variable name="id" select="@r:embed"/><img>
            <xsl:attribute name="src">
                <xsl:value-of select="/w:document/rels:Relationships/rels:Relationship[@Id=$id]/@Target"/>
            </xsl:attribute>
            <xsl:attribute name="width">
                <xsl:value-of select="round( ancestor::w:drawing[1]//wp:extent/@cx div 9525 )"/>
            </xsl:attribute>
            <xsl:attribute name="height">
                <xsl:value-of select="round( ancestor::w:drawing[1]//wp:extent/@cy div 9525 )"/>
            </xsl:attribute>
        </img></xsl:template>

    <!-- Links -->
    <xsl:template match="w:hyperlink"><xsl:variable name="id" select="@r:id"/><a><xsl:attribute name="href">
                <xsl:value-of select="/w:document/rels:Relationships/rels:Relationship[@Id=$id]/@Target"/>
            </xsl:attribute><xsl:apply-templates/></a></xsl:template>

    <!-- tables -->
    <xsl:template match="w:tbl">
        <item type="table" heading="false" style="table">
            <content>
                <table>
                    <xsl:apply-templates/>
                </table>
            </content>
        </item>
    </xsl:template>
    <xsl:template match="w:tr">
        <tr>
            <xsl:apply-templates/>
        </tr>
    </xsl:template>
    <xsl:template match="w:tc">
        <td>
            <xsl:value-of select="."/>
        </td>
    </xsl:template>

    <!-- skip contents of these fields -->
    <xsl:template match="rels:Relationships"/>
    <xsl:template match="text()"/>

</xsl:stylesheet>