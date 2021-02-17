/**
 * MIT licence
 * Version 1.2
 * Sjaak Priester, Amsterdam 28-08-2014 ... 15-02-2021.
 * https://sjaakpriester.nl
*/

function sortable(url, selector, rowTag) {
    document.querySelectorAll(selector).forEach(elmt => {
        let dummy = document.createElement(rowTag); // add dummy element to table or list
        if (rowTag === 'tr')    {   // if table, fill dummy with one td
            let td = document.createElement('td');
            td.setAttribute('colspan', 100);
            dummy.appendChild(td);
        }
        elmt.appendChild(dummy);
        dummy.classList.add('d-dummy');

        elmt.addEventListener("dragstart", e => {
            e.dataTransfer.effectAllowed = 'move';
            let src = e.target;     // drag started at row?

            if (src.tagName.toLowerCase() == rowTag) {  // yes
                src.classList.add('d-dragged');
                let next = src.nextElementSibling;

                [...elmt.children].forEach(row => {     // for all siblings...
                    row.classList.remove('d-dropped')
                    if (row != src && row != next) {    // ... except me and next
                        row.classList.add('d-drop')     // mark as drop area
                        row.ondragenter = e => {
                            e.preventDefault();
                            e.target.closest(rowTag).classList.add('d-over');
                        };
                        row.ondragleave = e => {
                            e.preventDefault();
                            e.target.closest(rowTag).classList.remove('d-over');
                        };
                        row.ondragover = e => {     // needed to accept drop
                            e.preventDefault();
                        };
                        row.ondrop = e => {
                            e.preventDefault();

                            src.classList.add('d-dropped');

                            elmt.insertBefore(src, e.target.closest(rowTag));   // insert source before drop
                            let pos = [...elmt.children].indexOf(src);  // calculate position
                            let key = src.dataset.key;      // retrieve key

                            // console.log('drop', key, 'pos', pos);

                            fetch(url, {    // send message
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-CSRF-Token': document.querySelector('[name="csrf-token"]').content
                                },
                                body: `key=${key}&pos=${pos}`
                            });
                        };
                    }
                });
            }
        });

        elmt.addEventListener("dragend", e => {     // restore defaults
            [...elmt.getElementsByTagName(rowTag)].forEach(row => {
                row.classList.remove('d-drop');
                row.classList.remove('d-over');
                row.classList.remove('d-dragged');
                row.ondragenter = null;
                row.ondragleave = null;
                row.ondragover = null;
                row.ondrop = null;
            });
        });
    });
}
