const fs = require('fs');
const path = require('path');
const { mdToPdf } = require('md-to-pdf');

async function generate() {
    try {
        const inputPath = path.resolve('docs/Manual_Usuario.md');
        const outputPath = path.resolve('docs/Manual_Usuario.pdf');

        console.log(`Reading from: ${inputPath}`);

        const pdf = await mdToPdf(
            { path: inputPath },
            {
                dest: outputPath,
                basedir: path.dirname(inputPath),
                pdf_options: {
                    format: 'A4',
                    margin: {
                        top: '20mm',
                        right: '20mm',
                        bottom: '20mm',
                        left: '20mm'
                    },
                    printBackground: true
                }
            }
        );

        if (pdf) {
            console.log('PDF created successfully at:', outputPath);
        }
    } catch (err) {
        console.error('Error generating PDF:', err);
    }
}

generate();
