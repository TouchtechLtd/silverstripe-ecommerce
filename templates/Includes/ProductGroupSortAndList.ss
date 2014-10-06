<% if ShowSortLinks %>
<small class="sortAndListLinks $AjaxDefinitions.ProductListAjaxifiedLinkClassName">

	<% if SortLinks %>
	<span class="sortOptions filterSortOptions"><% _t('ProductGroup.SORTBY','Sort by') %> <% loop SortLinks %> <a href="$Link" class="sortlink $LinkingMode">$Name</a> <% end_loop %></span>
	<% end_if %>
|
	<span class="viewLinks">
	<% if IsShowFullList %>
		<a href="$Link" class="viewChangeLink filterSortOptions"><% _t('ProductGroup.VIEW_DETAILS','View Detailed List') %></a></li>
	<% else %>
		<% if Products.MoreThanOnePage %>
		<a href="$ListAllLink" class="viewChangeLink filterSortOptions"><% _t('ProductGroup.LIST_ALL','List All') %> ($TotalCount)</a></li>
		<% else %>
		<span class="totalCout">$TotalCount  <% _t('ProductGroup.PRODUCTSFOUND','products found.') %></span>
		<% end_if %>
	</span>
	<% end_if %>
	<% if ResetPreferencesLink %> | <a href="$ResetPreferencesLink" class="viewChangeLink filterSortOptions"><% _t('ProductGroup.RESET_ALL','Reset Filters and Sort') %></a><% end_if %>
</small>
<% end_if %>
