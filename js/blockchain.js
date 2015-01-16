$(function(){
	$('#bc_searchBtn').on('click', function(event){
		$('#bc_searchFld').attr('disable','true');
		$.get('/ajax/blockchain/search.php?term=' + $('#bc_searchFld').val(), function(data){
			data = JSON.parse(data);
			window.location.href = "/blockchain/" + data.result;
		});
		event.preventDefault();
		return false;
	})
});