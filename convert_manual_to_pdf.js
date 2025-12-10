const fs = require('fs');
const path = require('path');
const { mdToPdf } = require('md-to-pdf');

const inputFile = String.raw`C:\Users\JaJo EkiZ\.gemini\antigravity\brain\0f6a6be3-29ba-4c3d-902f-e204bc4dae82\user_manual.md`;
const outputFile = String.raw`C:\Users\JaJo EkiZ\.gemini\antigravity\brain\0f6a6be3-29ba-4c3d-902f-e204bc4dae82\Manual_Usuario_Recova_Rentals.pdf`;

async function convertToPdf() {
    try {
        console.log('Converting markdown to PDF...');
        console.log('Input:', inputFile);
        console.log('Output:', outputFile);

        const pdf = await mdToPdf(
            { path: inputFile },
            {
                dest: outputFile,
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

        console.log('✅ PDF generated successfully!');
        console.log('Location:', pdf.filename);
    } catch (error) {
        console.error('❌ Error generating PDF:', error);
        process.exit(1);
    }
}

convertToPdf();
