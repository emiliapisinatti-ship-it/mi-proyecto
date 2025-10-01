const bagBtn = document.querySelector('.icon-btn.bag');
function addToBag(){ bagBtn.dataset.count = (+bagBtn.dataset.count || 0) + 1; }
