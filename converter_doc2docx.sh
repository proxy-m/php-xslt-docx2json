#!/bin/bash
export sourceFile=/path/to/source/document.doc
export outputDirectory=/output/folder/
/usr/bin/libreoffice --headless --convert-to docx ${sourceFile} --outdir ${outputDirectory}
