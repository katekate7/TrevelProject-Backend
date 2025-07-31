pipeline {
    agent {
        label "${AGENT}"
    }

    stages {
        stage("Continuous Integration / Intégration Continue") {
            steps {
                git branch: "main", url: "https://github.com/katekate7/TrevelProject.git"
                sh "composer install"
            }
        }
        stage("Continuous Delivery / Livraison Continue") {
            steps {
                sh "docker build -f Dockerfile -t ${DOCKERHUB_USERNAME}/travel-backend ."
                sh "docker login -u ${DOCKERHUB_USERNAME} -p ${DOCKER_PASSWORD}" // Créer un PAT sur Docker Hub : https://app.docker.com/settings/personal-access-tokens
                sh "docker push ${DOCKERHUB_USERNAME}/travel-backend"
            }
        }
    }
}