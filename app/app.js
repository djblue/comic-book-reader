var app = angular.module('app', ['ngRoute'])

app.config(function ($routeProvider) {
  $routeProvider
    .when('/folders/:id', {
        templateUrl: '/partials/folder.html'
    })
    .when('/viewer/:id/:page', {
        templateUrl: '/partials/viewer.html'
    })
    .otherwise({ redirectTo: '/folders/0' })
})

app.service('Data',

function ($q, $http) {

  var books = $q.defer()

  $http.get('/api/books').success(function (data) {
    books.resolve(data)
  })

  var folders = $q.defer()

  $http.get('/api/folders').success(function (data) {
    folders.resolve(data)
  })

  return {
    books: books.promise,
    folders: folders.promise
  }

})

app.controller('FolderController',

function ($http, $scope, $routeParams, Data) {

  Data.folders.then(function (folders) {

    $scope.countFolders = _.memoize(function (id) {
      var len = _.filter(folders, function (folder) {
        return Number(folder.parent) == Number(id)
      }).length
      return len
    })

    $scope.current = _.find(folders, function (folder) {
      return folder.id ==  $routeParams.id
    })

    $scope.parent = (!!$scope.current)? $scope.current.parent : -1

    $scope.folders = _.filter(folders, function (folder) {
      return folder.parent ==  $routeParams.id
    })

  })

  Data.books.then(function (books) {
    $scope.countBooks = _.memoize(function (id) {
      var len = _.filter(books, function (book) {
        return Number(book.folder) == Number(id)
      }).length
      return len
    })

    $scope.books = _.filter(books, function (book) {
      return book.folder ==  $routeParams.id;
    })
  })

})

// directive to capture global keyup events from
app.directive('onKeyup', function () {
  return function ($scope, $el, attrs) {
    $(window).off('keyup').on('keyup', function (e) {
      $scope.$eval(attrs.onKeyup, { $event: e })
    })
  }
})

// directive to capture global keypress events from
app.directive('onKeypress', function () {
  return function ($scope, $el, attrs) {
    $(window).off('keypress').on('keypress', function (e) {
      $scope.$eval(attrs.onKeypress, { $event: e })
    })
  }
})

app.controller('ViewerController',

function ($http, $scope, $routeParams, $location, Data) {

  var $window = $(window)

  // kinda hacky way to always start at the top of the page
  $window.scrollTop()

  // function to help redirect pages
  var redirect = function (book, page) {
    $scope.$apply(function () {
      $location.path('/viewer/'+book+'/'+page)
    })
  }

  Data.books.then(function (books) {
    $scope.book = _.find(books, function (book) {
      return book.id == $routeParams.id
    })

    $scope.id = $routeParams.id
    $scope.max = Number($scope.book.pages) - 1
    $scope.page = Number($routeParams.page)

    if ($scope.page < 0) {
      redirect($scope.id, 0)
    } else if ($scope.page > $scope.max) {
      redirect($scope.id, $scope.max)
    }
  })

  // useful key codes
  var codes = {
    left: 37,
    right: 39,
    esc: 27,
    q: 81,
    h: 72,
    j: 106,
    k: 107,
    l: 76,
    u: 85
  }

  // callback for user key up event 
  $scope.keyup = function (e) {
    if (e.shiftKey) {
      switch (e.keyCode) {
        // go to previous page
        case codes.h:
        case codes.left: // <-
          redirect($scope.id, 0)
          break;
        // go to next page
        case codes.l:
        case codes.right: // ->
          redirect($scope.id, $scope.max)
          break
      }
    } else {
      switch (e.keyCode) {
        // go to previous page
        case codes.h:
        case codes.left: // <-
            redirect($scope.id, $scope.page - 1)
            break;
        // go to next page
        case codes.l:
        case codes.right: // ->
            redirect($scope.id, $scope.page + 1)
            break;
        // quite the viewer
        case codes.q:
        case codes.esc:
          $scope.$apply(function () {
            $location.path('/folders/'+$scope.book.folder)
          })
          break;
        case codes.u:
          $window.history.back()
          break
      }
    }
  }

  // callback for user key press event
  $scope.keypress = function (e) {

    // access current window scroll position
    var y       = $window.scrollTop()
      , dy      = 40  // offset

    switch (e.keyCode) {
      // scroll up the page
      case codes.k:
        $window.scrollTop(y - dy)
        break
      // scroll down the page
      case codes.j:
        $window.scrollTop(y + dy)
        break
    }
  }

})
