// TODO: nice comment explaining where to find info on this file

var punditConfig = {

    debugAllModules: false,

    annotationServerBaseURL : 'http://as.thepund.it:8080/annotationserver/',

    vocabularies: [

	'http://metasound.dibet.univpm.it/marinelives/Marinelives-Ships.json',
    'http://metasound.dibet.univpm.it/marinelives/Marinelives-Persons.json',
    'http://metasound.dibet.univpm.it/marinelives/Marinelives-Relations.json'

    ],

    useBasicRelations: false,

    modules: {
        
        'pundit.Help': {
            introductionFile: 'http://metasound.dibet.univpm.it/marinelives/marinelive-introduction.html',
            introductionWindowTitle: 'Welcome to Pundit! :)',
            showIntroductionAtLogin: true
        },
        'pundit.NamedContentHandler': {
            active: false
        },
        
        'pundit.NotebookManager': { 
            active: true, 
            notebookSharing: false,
            notebookActivation: false,
            showFilteringOptions: false,
            askBaseURL: 'http://ask.as.thepund.it/#/myNotebooks/'
        },
        
        'selectors': {
            
            'Freebase': {
                name: 'freebase', label: 'Freebase', active: true
            },
            'DBPedia': {
                name: 'dbpedia', label: 'DBPedia', active: true
            },
            'KorboBasket': {
                name: 'korbo', label: 'Korbo', active: false
            },
            'Wordnet': {
                name: 'wordnet', label: 'Word Net', active: false
            },
            'Europeana': {
                name: 'europeana', label: 'Europeana', active: false
            },
            'EuropeanaEDM': {
                name: 'europeanaedm', label: 'Europeana EDM', active: false
            },
            // DEBUG: not ready for prime time, keep it active = false !
            'BibServer': {
                name: 'bibserver', label: 'BibServer', active: false
            }
            
        },
        
        'annotators': {}
    }

};
