pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        skipDefaultCheckout(true)
    }

    environment {
        GIT_REPO           = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    /*
      IMPORTANT:
      - pollSCM is REQUIRED for tag-based triggering
      - githubPush() is NOT reliable for tag-only pushes
    */
    triggers {
        pollSCM('H/5 * * * *')
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT (BRANCH + TAG) ---------------- */
        stage('Checkout Code') {
            steps {
                script {
                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: '**']],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID,
                            refspec: '+refs/heads/*:refs/remotes/origin/* +refs/tags/*:refs/tags/*'
                        ]]
                    ])

                    // Detect TAG
                    def tagName = sh(
                        script: "git describe --tags --exact-match 2>/dev/null || true",
                        returnStdout: true
                    ).trim()

                    if (tagName) {
                        env.TRIGGER_TYPE = "TAG"
                        env.GIT_TAG = tagName
                    } else {
                        env.TRIGGER_TYPE = "BRANCH"
                        env.GIT_BRANCH = sh(
                            script: "git rev-parse --abbrev-ref HEAD",
                            returnStdout: true
                        ).trim()
                    }

                    echo "Trigger Type: ${env.TRIGGER_TYPE}"
                    echo "Branch: ${env.GIT_BRANCH ?: 'N/A'}"
                    echo "Tag: ${env.GIT_TAG ?: 'N/A'}"
                }
            }
        }

        /* ---------------- ENV DECISION ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    /* -------- STAGING -------- */
                    if (env.TRIGGER_TYPE == "BRANCH" && env.GIT_BRANCH == "staging") {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.IMAGE_TAG = "staging-" + sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                    }

                    /* -------- PRODUCTION (TAG ONLY) -------- */
                    else if (env.TRIGGER_TYPE == "TAG") {

                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.IMAGE_TAG = env.GIT_TAG

                    }

                    /* -------- BLOCK EVERYTHING ELSE -------- */
                    else {
                        error("""
❌ Build blocked

Allowed triggers:
- STAGING  → git push origin staging
- PROD     → git push origin <tag>

Detected:
- Trigger : ${env.TRIGGER_TYPE}
- Branch  : ${env.GIT_BRANCH ?: 'N/A'}
- Tag     : ${env.GIT_TAG ?: 'N/A'}
""")
                    }

                    echo """
✅ Deployment Approved
---------------------
Environment : ${env.DEPLOY_ENV}
Image       : ${env.IMAGE_NAME}
Tag         : ${env.IMAGE_TAG}
"""
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh "echo ${DOCKER_PASS} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            steps {
                script {
                    def image = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                        docker build --no-cache -t ${image} .
                        docker push ${image}
                        docker logout
                    """
                }
            }
        }
    }
}
