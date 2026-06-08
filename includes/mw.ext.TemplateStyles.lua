local TemplateStyles = {}
local php

function TemplateStyles.link( title )
	return '<templatestyles src="' .. mw.text.encode( title ) .. '"/>'
end

function TemplateStyles.setupInterface( options )
	-- Boilerplate
	TemplateStyles.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register this library in the "mw" global
	mw = mw or {}
	mw.ext = mw.ext or {}
	mw.ext.TemplateStyles = TemplateStyles

	package.loaded['mw.ext.TemplateStyles'] = TemplateStyles
end

return TemplateStyles
