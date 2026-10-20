(function(){
	var tabs=document.querySelectorAll('.fc-tabs button'),panes=document.querySelectorAll('.fc-pane');
	function bars(p){p.querySelectorAll('.fc-bar i').forEach(function(i){i.style.width='0';requestAnimationFrame(function(){requestAnimationFrame(function(){i.style.width=i.dataset.w})})})}
	function show(id){
		tabs.forEach(function(t){t.classList.toggle('on',t.dataset.tab===id)});
		panes.forEach(function(p){var on=p.id==='fc-'+id;p.classList.toggle('on',on);if(on)bars(p)});
		try{history.replaceState(null,'','#'+id)}catch(e){}
	}
	tabs.forEach(function(t){t.addEventListener('click',function(){show(t.dataset.tab)})});
	document.querySelectorAll('[data-go]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();show(a.dataset.go);window.scrollTo({top:0,behavior:'smooth'})})});
	window.addEventListener('hashchange',function(){show((location.hash||'#overview').slice(1))});
	show((location.hash||'#overview').slice(1));

	// "I'm interested" vote of the Servers tab.
	var box = document.querySelector('.fc-vote');
	if (box && window.flash_cache_vote) {
		var cfg = window.flash_cache_vote, msg = box.querySelector('.fc-vote-msg');
		var consent = box.querySelector('.fc-vote-consent-box'), email = box.querySelector('.fc-vote-email');
		consent.addEventListener('change', function(){ email.hidden = !consent.checked; });
		var send = function(data, done){
			var body = new URLSearchParams(Object.assign({action: 'flash_cache_vote', nonce: cfg.nonce}, data));
			msg.textContent = cfg.working;
			fetch(cfg.ajax_url, {method: 'POST', credentials: 'same-origin', body: body})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (res && res.success) { msg.textContent = ''; done(res.data || {}); }
					else { msg.textContent = (res && res.data && res.data.message) || cfg.failed; }
				})
				.catch(function(){ msg.textContent = cfg.failed; });
		};
		box.addEventListener('click', function(e){
			var btn = e.target.closest('[data-vote]');
			if (!btn) { return; }
			if (btn.dataset.vote === 'vote') {
				send({do: 'vote'}, function(d){
					box.querySelector('.fc-vote-ask').hidden = true;
					box.querySelector('.fc-vote-thanks').hidden = false;
					box.querySelector('.fc-vote-total').textContent = d.label || '';
				});
			} else {
				send({do: 'details', note: box.querySelector('.fc-vote-note').value, email: email.value, consent: consent.checked ? 1 : 0}, function(){
					box.querySelector('.fc-vote-details').hidden = true;
					msg.textContent = cfg.sent;
				});
			}
		});
	}
})();
