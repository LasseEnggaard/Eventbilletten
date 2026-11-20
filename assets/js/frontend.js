(function(){
    document.querySelectorAll('[data-eb-list]').forEach(function(wrap){
        var list = document.getElementById(wrap.dataset.ebList);
        if(!list) return;
        var state = {};

        function applyFilters(){
            list.querySelectorAll('.jg-ticket').forEach(function(ticket){
                var visible = true;
                for(var dim in state){
                    var val = state[dim];
                    if(val === 'alle') continue;
                    if(dim === 'category'){
                        if((ticket.dataset.categories || '').split('|').indexOf(val) === -1){ visible = false; break; }
                    } else if(ticket.dataset[dim] !== val){ visible = false; break; }
                }
                ticket.style.display = visible ? '' : 'none';
            });
        }

        wrap.querySelectorAll('.jg-filter').forEach(function(filter){
            var dim = filter.dataset.dim;
            state[dim] = 'alle';

            if(dim === 'region'){
                var hash = window.location.hash.replace('#','');
                if(hash && filter.querySelector('[data-value="'+hash+'"]')) state[dim] = hash;
            }

            function setActive(val){
                filter.querySelectorAll('.jg-filter-btn').forEach(function(b){ b.classList.remove('active'); });
                var btn = filter.querySelector('[data-value="'+val+'"]') || filter.querySelector('[data-value="alle"]');
                if(btn) btn.classList.add('active');
            }
            setActive(state[dim]);

            filter.querySelectorAll('.jg-filter-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var val = this.dataset.value;
                    state[dim] = val;
                    setActive(val);
                    applyFilters();
                    if(dim === 'region'){
                        history.replaceState(null, '', val === 'alle' ? window.location.pathname + window.location.search : '#' + val);
                    }
                });
            });
        });

        applyFilters();
    });

    var popup = document.getElementById('jg-popup');
    if(!popup) return;
    var content = popup.querySelector('.jg-popup-content');

    function close(){ popup.classList.remove('open'); }

    document.addEventListener('click', function(e){
        var trigger = e.target.closest('.jg-readmore');
        if(trigger){
            content.innerHTML = trigger.dataset.content;
            popup.classList.add('open');
            return;
        }
        if(e.target === popup || e.target.closest('.jg-popup-close')) close();
    });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') close(); });
})();
