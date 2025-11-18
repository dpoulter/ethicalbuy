/**
 * scripts.js
 *
 Global JavaScript, if any.
 */
 
function removeShare(symbol){
	$.ajax({
      type: 'POST',
      url: 'removeShare.php',
      data: {'symbol':symbol}
      });

}

function killMe(el){
    return el.parentNode.removeChild(el);
}
function getParentByTagName(el,tag){
    tag=tag.toLowerCase();
    while(el&&el.nodeName.toLowerCase()!=tag){
        el=el.parentNode;
    }
    return el||null;
}
function delRow(el){
    killMe(getParentByTagName(el,'tr'));
}

/**
 * Searches database for typeahead's suggestions.
 */

$( document ).ready(function() {
     /*    var tbl = $("#tbl");
         $("#btnAddrow").click(function(){
             $("#tbl").append("<tr><td></td><td><input type=text id='search' class='form-control typeahead' placeholder='symbol' name='symbol[]'></input></td><td><select id='trx_type' name='trx_type[]'><option value='SELL'>SELL</option><option value='BUY'>BUY</option></select></td><td><input type=text name='shares[]'</input></td><td><input name='price_paid[]'</input></td><td><input type=text name='commission[]'</input></td><td><input type=date name='purchase_date[]'</input></td><td><input type=checkbox value='Y' name='delete[]'</input></td></tr>");
          });
       var tbl = $("#tblDiv");
         $("#btnDivAdd").click(function(){
             $("#tblDiv").append("<tr><td></td><td><input type=text id='search' class=' form-control typeahead' placeholder='symbol' name='symbol[]'></input></td><td><input type=date name='dividend_date[]'</input></td><td><input name='amount[]'</input></td><td></td></tr>");
          });
	  */




function searches(query, cb)
{
    // get shares matching query (asynchronously)
   matches=["Alabama", 'Alaska', 'Arizona', 'Arkansas', 'California',
  'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii',
  'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana',
  'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota',
  'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire',
  'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota',
  'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island',
  'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
  'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'
];
	
   cb(matches);
}

 var countries = new Bloodhound({
  datumTokenizer: Bloodhound.tokenizers.whitespace,
  queryTokenizer: Bloodhound.tokenizers.whitespace,
  // url points to a json file that contains an array of country names, see
  // https://github.com/twitter/typeahead.js/blob/gh-pages/data/countries.json
  prefetch: 'symbols10.php'
});

$('#the-basics .typeahead').typeahead({
  hint: true,
  highlight: true,
  minLength: 1
},
{
  name: 'states',
  source: searches//substringMatcher(states)
});

$('#search .typeahead').typeahead({
  hint: true,
  highlight: true,
  minLength: 1
},
{
  name: 'symbols',
  source: countries
});

function sync_bh(datums) {
  console.log('datums from `local`, `prefetch`, and `#add`');
  console.log(datums);
}

function async_bh(datums) {
  console.log('datums from `remote`');
  console.log(datums);
}

$('#lookup_form').on('submit', function(e) {
    e.preventDefault();
    var symbol_input = $('#symbol_text').val();
    var matched_result = countries.get(symbol_input, sync_bh, async_bh);
    if (matched_result.length > 0) {
        this.submit();
    }
});

$('.glyphicon-edit').click(function(states){
  alert('Are you sure');
});	 

});
