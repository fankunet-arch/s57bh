import { STATE } from '../state.js';
import { t, fmtEUR } from '../utils.js';

/**
 * Parses a print template command object and returns formatted lines.
 * @param {object} command - The command object from the template JSON.
 * @param {object} data - The data context (e.g., EOD report data, invoice data).
 * @returns {string[]} An array of formatted lines for this command.
 */
function parseCommand(command, data) {
    const lines = [];
    const type = command.type;
    const value = command.value || '';
    const align = command.align || 'left'; // left, center, right
    const size = command.size || 'normal'; // normal, double
    const key = command.key || '';
    const boldValue = command.bold_value || false;

    // Helper to replace placeholders like {variable_name}
    const replacePlaceholders = (text) => {
        if (!text || typeof text !== 'string') return '';
        return text.replace(/\{([\w_]+)\}/g, (match, key) => {
            // Simple direct access, could be enhanced for nested keys later
            return data[key] !== undefined ? data[key] : match;
        });
    };

    // Helper for alignment (assuming 40 chars width for simulation)
    const alignText = (text) => {
        const width = size === 'double' ? 20 : 40; // Approx width
        const len = text.length;
        if (align === 'center') {
            const padding = Math.max(0, Math.floor((width - len) / 2));
            return ' '.repeat(padding) + text;
        } else if (align === 'right') {
            const padding = Math.max(0, width - len);
            return ' '.repeat(padding) + text;
        }
        return text; // left align is default
    };

    switch (type) {
        case 'text':
            lines.push(alignText(replacePlaceholders(value)));
            break;
        case 'kv': // Key-Value pair
            const replacedKey = replacePlaceholders(key);
            const replacedValue = replacePlaceholders(value);
            const kvText = `${replacedKey}: ${boldValue ? '**' : ''}${replacedValue}${boldValue ? '**' : ''}`; // Use markdown for bold simulation
            lines.push(alignText(kvText)); // Simple alignment for now
            break;
        case 'divider':
            const char = command.char || '-';
            const width = size === 'double' ? 20 : 40;
            lines.push(char.repeat(width));
            break;
        case 'feed':
            const lineCount = command.lines || 1;
            for (let i = 0; i < lineCount; i++) {
                lines.push('');
            }
            break;
        case 'cut':
            lines.push('--- CUT ---'); // Simulation placeholder
            break;
        case 'qr_code':
            lines.push(`[QR Code: ${replacePlaceholders(value)}]`);
            break;
        case 'items_header': // Fixed header for item loop
             lines.push(alignText("商品 QTY 单价 总价")); // Adjust alignment later
             lines.push("-".repeat(40));
             break;
        case 'items_loop': // Placeholder, real implementation needs item iteration
             lines.push("[Item Loop Start]");
             // In a real scenario, loop through data.items and parse command.content for each
             lines.push(" Sample Item 1 x1 €5.00 €5.00");
             lines.push(" Sample Item 2 x2 €3.50 €7.00");
             lines.push("[Item Loop End]");
             break;
         case 'item_line': // Placeholder, used inside items_loop
             lines.push(" (Item Line Placeholder) ");
             break;
         case 'item_customizations': // Placeholder, used inside items_loop
             lines.push(" (Customizations Placeholder) ");
             break;
        // Add more command types as needed (e.g., barcode, image)
        default:
            lines.push(`[Unknown command type: ${type}]`);
    }

    // Apply size modification (simple line duplication for simulation)
    if (size === 'double' && lines.length > 0) {
        // Just add a marker for now, actual double-height/width is printer specific
        return lines.map(line => `[Double Size] ${line}`);
    }

    return lines;
}


/**
 * Simulates printing a receipt based on data and a template JSON.
 * @param {object} data - The data context (e.g., EOD report data).
 * @param {object[]} template - The template structure (array of command objects).
 */
export async function printReceipt(data, template) {
    if (!data || !template || !Array.isArray(template)) {
        console.error('Invalid data or template for printing.');
        toast(t('print_failed') + ': Invalid input');
        return;
    }

    const formattedLines = [];
    template.forEach(command => {
        formattedLines.push(...parseCommand(command, data));
    });

    const output = formattedLines.join('\n');
    console.log("--- PRINT SIMULATION ---");
    console.log(output);
    console.log("------------------------");

    // --- Show in Modal for User ---
    const previewModalEl = document.getElementById('printPreviewModal');
    if (previewModalEl) {
         document.getElementById('printPreviewBody').textContent = output;
         const modal = bootstrap.Modal.getOrCreateInstance(previewModalEl);
         modal.show();
    } else {
        // Fallback if modal doesn't exist (should not happen with ensureModalsExist)
        alert("Print Preview:\n\n" + output);
    }

    // In a real application, here you would send `formattedLines` or specific printer commands
    // to the native layer (APK) or a connected printer service.
    // e.g., if using a bridge in WebView:
    // if (window.AndroidBridge && window.AndroidBridge.printLines) {
    //     window.AndroidBridge.printLines(JSON.stringify(formattedLines));
    // } else {
    //     console.warn('Printing bridge not available.');
    //     // Show simulation modal anyway
    // }
}


/**
 * Ensure the print preview modal exists in the DOM.
 * Should be called once during application initialization.
 */
export function initializePrintSimulator() {
    if (!document.getElementById('printPreviewModal')) {
       document.body.insertAdjacentHTML('beforeend', `
         <div class="modal fade" id="printPreviewModal" tabindex="-1" aria-labelledby="printPreviewModalLabel" aria-hidden="true">
           <div class="modal-dialog modal-dialog-scrollable">
             <div class="modal-content modal-sheet">
               <div class="modal-header">
                 <h5 class="modal-title" id="printPreviewModalLabel">${t('print_preview_title')}</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${t('close')}"></button>
               </div>
               <div class="modal-body" id="printPreviewBody" style="font-family: monospace; white-space: pre; font-size: 0.8rem;">
                 </div>
               <div class="modal-footer">
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${t('close')}</button>
               </div>
             </div>
           </div>
         </div>`);
      }
}
