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
})();
