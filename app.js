var app = angular.module('app', ['ngRoute'])
.config(['$routeProvider', 
    function ($routeProvider) {
        $routeProvider
            .when('/folders/:id', {
                templateUrl: '/partials/folder.html'
            })
            .when('/viewer/:id/:page', {
                templateUrl: '/partials/viewer.html'
            })
            .otherwise({ redirectTo: '/folders/0' });
    }]);


app.service('Data', ['$q', '$http',

function ($q, $http) {

    var books = $q.defer();
    
    $http.get('/api/books').success(function (data) {
        books.resolve(data);
    });

    var folders = $q.defer();

    $http.get('/api/folders').success(function (data) {
        folders.resolve(data);
    });
   
    return {
        books: books.promise,
        folders: folders.promise
    };

}]);

app.controller('FolderController', ['$http', '$scope', '$routeParams', 'Data',

function ($http, $scope, $routeParams, Data) {

    $scope.clear = function () {
        $scope.search = "";
    };

    Data.folders.then(function (folders) {

        $scope.current = _.find(folders, function (folder) {
            return folder.id ==  $routeParams.id;
        });

        $scope.parent = (!!$scope.current)? $scope.current.parent : -1;

        $scope.folders = _.filter(folders, function (folder) {
            return folder.parent ==  $routeParams.id;
        });

    });

    $scope.search = "";

    Data.books.then(function (books) {

        $scope.books = _.filter(books, function (book) {
            return book.folder ==  $routeParams.id;
        });

        $scope.$watch('search', function () {
            if ($scope.search != "") {
                $scope.books = _.filter(books, function (book) {
                    return book.label.toLowerCase().indexOf($scope.search.toLowerCase()) > -1;
                });
            } else {
                $scope.books = _.filter(books, function (book) {
                    return book.folder ==  $routeParams.id;
                });
            }
        });

    });


}]);

app.controller('ViewerController', ['$http', '$scope', '$routeParams', '$location', 'Data',

function ($http, $scope, $routeParams, $location, Data) {

    Data.books.then(function (books) {
        $scope.book = _.find(books, function (book) {
            return book.id == $routeParams.id;
        });

        $scope.id = $routeParams.id;
        $scope.max = Number($scope.book.pages) - 1;
        $scope.page = Number($routeParams.page);

        if ($scope.page < 0) {
            $location.path('/viewer/'+$scope.id+'/0');
        } else if ($scope.page > $scope.max) {
            $location.path('/viewer/'+$scope.id+'/'+$scope.max);
        }

    });
    
}]);
